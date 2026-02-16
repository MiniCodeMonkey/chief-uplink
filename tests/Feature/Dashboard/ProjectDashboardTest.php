<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('Dashboard Page', function () {
    it('renders the dashboard page', function () {
        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard'));
    });

    it('redirects unauthenticated users to login', function () {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    });

    it('root path redirects to dashboard', function () {
        $response = $this->actingAs($this->user)->get('/');

        $response->assertRedirect(route('dashboard'));
    });
});

describe('Dashboard Project Cards', function () {
    it('includes projects in device shared props', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device)->running()->create([
            'project_name' => 'My App',
            'project_slug' => 'my-app',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(1);
            expect($devices[0]['projects'])->toHaveCount(1);
            expect($devices[0]['projects'][0]['project_name'])->toBe('My App');
            expect($devices[0]['projects'][0]['project_slug'])->toBe('my-app');
        });
    });

    it('includes status field for each project', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device)->running()->create(['project_slug' => 'proj-running']);
        CachedProjectState::factory()->for($device)->idle()->create(['project_slug' => 'proj-idle']);
        CachedProjectState::factory()->for($device)->error()->create(['project_slug' => 'proj-error']);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            $projects = $devices[0]['projects'];
            expect($projects)->toHaveCount(3);

            $statuses = array_column($projects, 'status');
            expect($statuses)->toContain('running');
            expect($statuses)->toContain('idle');
            expect($statuses)->toContain('error');
        });
    });

    it('includes story progress for running projects', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device)->running()->create([
            'stories_completed' => 3,
            'stories_total' => 10,
            'current_prd_name' => 'Feature PRD',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            $project = $devices[0]['projects'][0];
            expect($project['stories_completed'])->toBe(3);
            expect($project['stories_total'])->toBe(10);
            expect($project['current_prd_name'])->toBe('Feature PRD');
        });
    });

    it('includes git branch for projects', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device)->create([
            'git_branch' => 'feature/auth',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['projects'][0]['git_branch'])->toBe('feature/auth');
        });
    });

    it('includes active_sessions for projects', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device)->running()->create([
            'active_sessions' => 2,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['projects'][0]['active_sessions'])->toBe(2);
        });
    });

    it('includes recent_activity for projects', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        $activity = [
            ['event' => 'Run completed', 'timestamp' => now()->subHours(1)->toISOString()],
            ['event' => 'PRD updated', 'timestamp' => now()->subMinutes(30)->toISOString()],
        ];
        CachedProjectState::factory()->for($device)->create([
            'recent_activity' => $activity,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            $recentActivity = $devices[0]['projects'][0]['recent_activity'];
            expect($recentActivity)->toHaveCount(2);
            expect($recentActivity[0]['event'])->toBe('Run completed');
        });
    });

    it('does not include projects from other users devices', function () {
        $otherUser = User::factory()->create();
        $otherDevice = DeviceAuthorization::factory()->for($otherUser)->online()->create();
        CachedProjectState::factory()->for($otherDevice)->create([
            'project_name' => 'Other User Project',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(0);
        });
    });

    it('does not include projects from revoked devices', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->revoked()->create();
        CachedProjectState::factory()->for($device)->create([
            'project_name' => 'Revoked Device Project',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(0);
        });
    });

    it('shows multiple projects per device', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        foreach (['proj-a', 'proj-b', 'proj-c', 'proj-d', 'proj-e'] as $slug) {
            CachedProjectState::factory()->for($device)->create(['project_slug' => $slug]);
        }

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['projects'])->toHaveCount(5);
        });
    });

    it('shows empty devices array when user has no devices', function () {
        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(0);
        });
    });

    it('shows device with empty projects when device has no projects', function () {
        DeviceAuthorization::factory()->for($this->user)->online()->create();

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(1);
            expect($devices[0]['projects'])->toHaveCount(0);
        });
    });

    it('includes all project statuses correctly', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device)->running()->create(['project_slug' => 'running-project']);
        CachedProjectState::factory()->for($device)->idle()->create(['project_slug' => 'idle-project']);
        CachedProjectState::factory()->for($device)->error()->create(['project_slug' => 'error-project']);
        CachedProjectState::factory()->for($device)->paused()->create(['project_slug' => 'paused-project']);
        CachedProjectState::factory()->for($device)->noPrd()->create(['project_slug' => 'no-prd-project']);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            $projects = $devices[0]['projects'];
            expect($projects)->toHaveCount(5);

            $statusMap = [];
            foreach ($projects as $project) {
                $statusMap[$project['project_slug']] = $project['status'];
            }

            expect($statusMap['running-project'])->toBe('running');
            expect($statusMap['idle-project'])->toBe('idle');
            expect($statusMap['error-project'])->toBe('error');
            expect($statusMap['paused-project'])->toBe('paused');
            expect($statusMap['no-prd-project'])->toBe('no_prd');
        });
    });
});
