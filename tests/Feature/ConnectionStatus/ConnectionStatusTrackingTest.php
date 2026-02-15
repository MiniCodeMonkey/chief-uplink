<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Connection Status in Inertia Props', function () {
    it('includes connection_status field for each device', function () {
        DeviceAuthorization::factory()->for($this->user)->online()->create();

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0])->toHaveKey('connection_status');
        });
    });

    it('online device shows green status', function () {
        DeviceAuthorization::factory()->for($this->user)->online()->create([
            'device_name' => 'my-server',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('online');
            expect($devices[0]['is_online'])->toBeTrue();
        });
    });

    it('recently disconnected device shows reconnecting status within 60s', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'device_name' => 'reconnecting-server',
            'is_online' => false,
            'last_connected_at' => now()->subSeconds(10),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('reconnecting');
        });
    });

    it('device disconnected over 60s ago shows offline status', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'device_name' => 'offline-server',
            'is_online' => false,
            'last_connected_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('offline');
        });
    });

    it('never connected device shows never-connected status', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'device_name' => 'new-server',
            'is_online' => false,
            'last_connected_at' => null,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('never-connected');
        });
    });

    it('offline device includes last_connected_at for relative time display', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['last_connected_at'])->not->toBeNull();
            expect($devices[0]['connection_status'])->toBe('offline');
        });
    });

    it('status is visible on project detail pages too', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device)->create([
            'project_slug' => 'my-project',
            'project_name' => 'My Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/my-project');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0])->toHaveKey('connection_status');
            expect($devices[0]['connection_status'])->toBe('online');
        });
    });

    it('boundary: device disconnected exactly at 60s shows offline', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => now()->subSeconds(60),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('offline');
        });
    });

    it('device disconnected at 59s shows reconnecting', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => now()->subSeconds(59),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('reconnecting');
        });
    });
});

describe('Project State Cache Overwrite', function () {
    it('project_state message overwrites existing cached projects', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();

        // Create initial cached state
        CachedProjectState::factory()->for($device)->create([
            'project_slug' => 'old-project',
            'project_name' => 'Old Project',
            'status' => 'idle',
        ]);

        $controller = new \App\WebSocket\ChiefServerController(
            app(\App\Services\ServerConnectionManager::class)
        );

        // Simulate project_state message via reflection
        $method = new \ReflectionMethod($controller, 'handleProjectState');
        $method->setAccessible(true);
        $method->invoke($controller, $device->id, [
            'type' => 'project_state',
            'payload' => [
                'projects' => [
                    [
                        'project_slug' => 'old-project',
                        'project_name' => 'Old Project Updated',
                        'status' => 'running',
                        'git_branch' => 'main',
                        'stories_completed' => 3,
                        'stories_total' => 10,
                    ],
                ],
            ],
        ]);

        $cached = CachedProjectState::where('device_authorization_id', $device->id)
            ->where('project_slug', 'old-project')
            ->first();

        expect($cached)->not->toBeNull();
        expect($cached->project_name)->toBe('Old Project Updated');
        expect($cached->status)->toBe('running');
        expect($cached->stories_completed)->toBe(3);
        expect($cached->stories_total)->toBe(10);
    });

    it('project_state message adds new projects', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();

        $controller = new \App\WebSocket\ChiefServerController(
            app(\App\Services\ServerConnectionManager::class)
        );

        $method = new \ReflectionMethod($controller, 'handleProjectState');
        $method->setAccessible(true);
        $method->invoke($controller, $device->id, [
            'type' => 'project_state',
            'payload' => [
                'projects' => [
                    [
                        'project_slug' => 'new-project',
                        'project_name' => 'New Project',
                        'status' => 'idle',
                    ],
                ],
            ],
        ]);

        $cached = CachedProjectState::where('device_authorization_id', $device->id)
            ->where('project_slug', 'new-project')
            ->first();

        expect($cached)->not->toBeNull();
        expect($cached->project_name)->toBe('New Project');
    });

    it('project_state message removes projects no longer on server', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();

        CachedProjectState::factory()->for($device)->create([
            'project_slug' => 'removed-project',
            'project_name' => 'Removed Project',
        ]);

        CachedProjectState::factory()->for($device)->create([
            'project_slug' => 'kept-project',
            'project_name' => 'Kept Project',
        ]);

        $controller = new \App\WebSocket\ChiefServerController(
            app(\App\Services\ServerConnectionManager::class)
        );

        $method = new \ReflectionMethod($controller, 'handleProjectState');
        $method->setAccessible(true);
        $method->invoke($controller, $device->id, [
            'type' => 'project_state',
            'payload' => [
                'projects' => [
                    [
                        'project_slug' => 'kept-project',
                        'project_name' => 'Kept Project',
                        'status' => 'idle',
                    ],
                ],
            ],
        ]);

        $remaining = CachedProjectState::where('device_authorization_id', $device->id)->get();
        expect($remaining)->toHaveCount(1);
        expect($remaining[0]->project_slug)->toBe('kept-project');
    });

    it('project_state handles invalid payload gracefully', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();

        $controller = new \App\WebSocket\ChiefServerController(
            app(\App\Services\ServerConnectionManager::class)
        );

        $method = new \ReflectionMethod($controller, 'handleProjectState');
        $method->setAccessible(true);

        // Should not throw
        $method->invoke($controller, $device->id, [
            'type' => 'project_state',
            'payload' => ['projects' => 'invalid'],
        ]);

        // Also test with missing payload
        $method->invoke($controller, $device->id, [
            'type' => 'project_state',
        ]);

        expect(true)->toBeTrue(); // No exception thrown
    });

    it('project_state skips projects without project_slug', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();

        $controller = new \App\WebSocket\ChiefServerController(
            app(\App\Services\ServerConnectionManager::class)
        );

        $method = new \ReflectionMethod($controller, 'handleProjectState');
        $method->setAccessible(true);
        $method->invoke($controller, $device->id, [
            'type' => 'project_state',
            'payload' => [
                'projects' => [
                    ['project_name' => 'No Slug'],
                    ['project_slug' => 'has-slug', 'project_name' => 'Has Slug', 'status' => 'idle'],
                ],
            ],
        ]);

        $cached = CachedProjectState::where('device_authorization_id', $device->id)->get();
        expect($cached)->toHaveCount(1);
        expect($cached[0]->project_slug)->toBe('has-slug');
    });

    it('project_state only affects the target device projects', function () {
        $device1 = DeviceAuthorization::factory()->for($this->user)->online()->create();
        $device2 = DeviceAuthorization::factory()->for($this->user)->online()->create();

        CachedProjectState::factory()->for($device2)->create([
            'project_slug' => 'other-device-project',
        ]);

        $controller = new \App\WebSocket\ChiefServerController(
            app(\App\Services\ServerConnectionManager::class)
        );

        $method = new \ReflectionMethod($controller, 'handleProjectState');
        $method->setAccessible(true);
        $method->invoke($controller, $device1->id, [
            'type' => 'project_state',
            'payload' => [
                'projects' => [
                    ['project_slug' => 'device1-project', 'project_name' => 'D1 Project', 'status' => 'idle'],
                ],
            ],
        ]);

        // Device 2's project should be untouched
        $device2Projects = CachedProjectState::where('device_authorization_id', $device2->id)->get();
        expect($device2Projects)->toHaveCount(1);
        expect($device2Projects[0]->project_slug)->toBe('other-device-project');

        // Device 1 should have its new project
        $device1Projects = CachedProjectState::where('device_authorization_id', $device1->id)->get();
        expect($device1Projects)->toHaveCount(1);
        expect($device1Projects[0]->project_slug)->toBe('device1-project');
    });
});
