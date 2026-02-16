<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Dashboard Offline State', function () {
    it('renders dashboard with cached projects when server is offline', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->offline()->create();
        CachedProjectState::factory()->for($device)->running()->create([
            'project_name' => 'Offline Project',
            'project_slug' => 'offline-project',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(1);
            expect($devices[0]['connection_status'])->toBe('offline');
            expect($devices[0]['projects'])->toHaveCount(1);
            expect($devices[0]['projects'][0]['project_name'])->toBe('Offline Project');
        });
    });

    it('includes last_connected_at for offline devices', function () {
        $lastConnected = now()->subHours(2);
        $device = DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => $lastConnected,
        ]);
        CachedProjectState::factory()->for($device)->idle()->create(['project_slug' => 'test-proj']);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['last_connected_at'])->not->toBeNull();
            expect($devices[0]['connection_status'])->toBe('offline');
        });
    });

    it('shows cached projects with all statuses when offline', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->offline()->create();
        CachedProjectState::factory()->for($device)->running()->create(['project_slug' => 'proj-running']);
        CachedProjectState::factory()->for($device)->idle()->create(['project_slug' => 'proj-idle']);
        CachedProjectState::factory()->for($device)->error()->create(['project_slug' => 'proj-error']);
        CachedProjectState::factory()->for($device)->paused()->create(['project_slug' => 'proj-paused']);
        CachedProjectState::factory()->for($device)->noPrd()->create(['project_slug' => 'proj-noprd']);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('offline');
            expect($devices[0]['projects'])->toHaveCount(5);

            $statuses = array_column($devices[0]['projects'], 'status');
            expect($statuses)->toContain('running');
            expect($statuses)->toContain('idle');
            expect($statuses)->toContain('error');
            expect($statuses)->toContain('paused');
            expect($statuses)->toContain('no_prd');
        });
    });

    it('preserves story progress in cached state when offline', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->offline()->create();
        CachedProjectState::factory()->for($device)->running()->create([
            'stories_completed' => 5,
            'stories_total' => 12,
            'current_prd_name' => 'My Feature PRD',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            $project = $devices[0]['projects'][0];
            expect($project['stories_completed'])->toBe(5);
            expect($project['stories_total'])->toBe(12);
            expect($project['current_prd_name'])->toBe('My Feature PRD');
        });
    });
});

describe('Dashboard Never-Connected State', function () {
    it('shows never-connected status when device has never connected', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => null,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(1);
            expect($devices[0]['connection_status'])->toBe('never-connected');
            expect($devices[0]['last_connected_at'])->toBeNull();
            expect($devices[0]['projects'])->toHaveCount(0);
        });
    });

    it('shows never-connected device with no projects', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => null,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(1);
            expect($devices[0]['connection_status'])->toBe('never-connected');
            expect($devices[0]['projects'])->toHaveCount(0);
        });
    });
});

describe('Dashboard Reconnecting State', function () {
    it('shows reconnecting status when device recently disconnected', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => now()->subSeconds(30),
        ]);
        CachedProjectState::factory()->for($device)->idle()->create(['project_slug' => 'reconnect-proj']);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('reconnecting');
            expect($devices[0]['projects'])->toHaveCount(1);
        });
    });
});

describe('Dashboard Connection Status Transitions', function () {
    it('shows online status when device is online', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device)->running()->create(['project_slug' => 'online-proj']);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('online');
            expect($devices[0]['is_online'])->toBeTrue();
        });
    });

    it('transitions from online to offline preserves cached data', function () {
        // Create device with online state and projects
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device)->running()->create([
            'project_slug' => 'my-project',
            'project_name' => 'My Project',
            'stories_completed' => 3,
            'stories_total' => 8,
        ]);

        // Verify online state
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('online');
            expect($devices[0]['projects'][0]['project_name'])->toBe('My Project');
        });

        // Simulate going offline
        $device->update([
            'is_online' => false,
            'last_connected_at' => now()->subMinutes(5),
        ]);

        // Verify offline state still has cached data
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('offline');
            expect($devices[0]['projects'])->toHaveCount(1);
            expect($devices[0]['projects'][0]['project_name'])->toBe('My Project');
            expect($devices[0]['projects'][0]['stories_completed'])->toBe(3);
            expect($devices[0]['projects'][0]['stories_total'])->toBe(8);
        });
    });

    it('transitions from offline back to online', function () {
        // Start offline
        $device = DeviceAuthorization::factory()->for($this->user)->offline()->create();
        CachedProjectState::factory()->for($device)->running()->create([
            'project_slug' => 'my-project',
            'project_name' => 'My Project',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('offline');
        });

        // Simulate coming back online
        $device->update([
            'is_online' => true,
            'last_connected_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('online');
            expect($devices[0]['projects'])->toHaveCount(1);
        });
    });
});
