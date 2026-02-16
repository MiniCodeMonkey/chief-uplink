<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\RunHistory;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| Dashboard — Offline State Rendering
|--------------------------------------------------------------------------
*/

describe('Dashboard Offline State Rendering', function () {
    it('renders cached projects when all servers are offline', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->offline()->create([
            'last_connected_at' => now()->subHours(1),
        ]);

        CachedProjectState::factory()->for($device)->running()->create([
            'project_name' => 'Project A',
            'project_slug' => 'project-a',
            'stories_completed' => 3,
            'stories_total' => 10,
        ]);
        CachedProjectState::factory()->for($device)->idle()->create([
            'project_name' => 'Project B',
            'project_slug' => 'project-b',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(1);
            expect($devices[0]['connection_status'])->toBe('offline');
            expect($devices[0]['projects'])->toHaveCount(2);
        });
    });

    it('mixes online and offline devices on dashboard', function () {
        $onlineDevice = DeviceAuthorization::factory()->for($this->user)->online()->create([
            'device_name' => 'online-server',
        ]);
        $offlineDevice = DeviceAuthorization::factory()->for($this->user)->offline()->create([
            'device_name' => 'offline-server',
            'last_connected_at' => now()->subHours(2),
        ]);

        CachedProjectState::factory()->for($onlineDevice)->running()->create(['project_slug' => 'live-project']);
        CachedProjectState::factory()->for($offlineDevice)->idle()->create(['project_slug' => 'cached-project']);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(2);

            $online = collect($devices)->firstWhere('device_name', 'online-server');
            $offline = collect($devices)->firstWhere('device_name', 'offline-server');

            expect($online['connection_status'])->toBe('online');
            expect($online['projects'])->toHaveCount(1);
            expect($offline['connection_status'])->toBe('offline');
            expect($offline['projects'])->toHaveCount(1);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Project Detail — Offline State Rendering
|--------------------------------------------------------------------------
*/

describe('Project Detail Offline State', function () {
    it('renders project overview from cache when server is offline', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->offline()->create([
            'last_connected_at' => now()->subHours(1),
        ]);

        CachedProjectState::factory()->for($device)->running()->create([
            'project_slug' => 'offline-overview',
            'project_name' => 'Offline Overview',
            'current_prd_name' => 'Feature PRD',
            'stories_completed' => 4,
            'stories_total' => 8,
            'story_details' => [
                ['id' => 'US-001', 'title' => 'Auth', 'status' => 'completed'],
                ['id' => 'US-002', 'title' => 'Dashboard', 'status' => 'in_progress'],
            ],
            'git_branch' => 'feature/auth',
            'last_commit_hash' => 'abc1234',
            'last_commit_message' => 'feat: add auth',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/offline-overview');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $project = $page->toArray()['props']['project'];
            expect($project['status'])->toBe('running');
            expect($project['current_prd_name'])->toBe('Feature PRD');
            expect($project['stories_completed'])->toBe(4);
            expect($project['stories_total'])->toBe(8);
            expect($project['story_details'])->toHaveCount(2);
            expect($project['git_branch'])->toBe('feature/auth');
            expect($project['last_commit_hash'])->toBe('abc1234');
        });
    });

    it('renders run tab from cache when server is offline', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->offline()->create([
            'last_connected_at' => now()->subHours(1),
        ]);

        CachedProjectState::factory()->for($device)->running()->create([
            'project_slug' => 'offline-run',
            'stories_completed' => 2,
            'stories_total' => 5,
            'story_details' => [
                ['id' => 'US-001', 'title' => 'Story 1', 'status' => 'completed'],
                ['id' => 'US-002', 'title' => 'Story 2', 'status' => 'completed'],
                ['id' => 'US-003', 'title' => 'Story 3', 'status' => 'in_progress'],
                ['id' => 'US-004', 'title' => 'Story 4', 'status' => 'pending'],
                ['id' => 'US-005', 'title' => 'Story 5', 'status' => 'pending'],
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/projects/offline-run/run');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $project = $page->toArray()['props']['project'];
            expect($project['story_details'])->toHaveCount(5);
            expect($project['stories_completed'])->toBe(2);
        });
    });

    it('renders diffs tab from cache when server is offline', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->offline()->create([
            'last_connected_at' => now()->subHours(1),
        ]);

        CachedProjectState::factory()->for($device)->running()->create([
            'project_slug' => 'offline-diffs',
            'story_details' => [
                ['id' => 'US-001', 'title' => 'Auth', 'status' => 'completed'],
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/projects/offline-diffs/diffs');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Diffs')
            ->where('projectSlug', 'offline-diffs')
            ->has('deviceId')
        );
    });
});

/*
|--------------------------------------------------------------------------
| Run History — Available Offline
|--------------------------------------------------------------------------
*/

describe('Run History Available Offline', function () {
    it('shows run history when server is offline', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->offline()->create([
            'last_connected_at' => now()->subHours(3),
        ]);

        CachedProjectState::factory()->for($device)->idle()->create([
            'project_slug' => 'offline-history',
        ]);

        RunHistory::factory()->completed()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'offline-history',
            'prd_name' => 'Completed Run',
            'stories_completed' => 10,
            'stories_total' => 10,
        ]);

        RunHistory::factory()->failed()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'offline-history',
            'prd_name' => 'Failed Run',
            'error_message' => 'Tests failed',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/offline-history/run');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 2)
        );
    });

    it('shows recent runs on overview when server is offline', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->offline()->create([
            'last_connected_at' => now()->subHours(1),
        ]);

        CachedProjectState::factory()->for($device)->idle()->create([
            'project_slug' => 'offline-recent',
        ]);

        RunHistory::factory()->completed()->count(3)->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'offline-recent',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/offline-recent');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('recentRuns', 3)
        );
    });
});

/*
|--------------------------------------------------------------------------
| Empty States
|--------------------------------------------------------------------------
*/

describe('Empty States', function () {
    it('shows empty dashboard when no devices exist', function () {
        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices)->toHaveCount(0);
        });
    });

    it('shows empty projects when device has no cached projects', function () {
        DeviceAuthorization::factory()->for($this->user)->online()->create();

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['projects'])->toHaveCount(0);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Connection Status Transitions
|--------------------------------------------------------------------------
*/

describe('Connection Status Transitions', function () {
    it('correctly shows reconnecting for recently-disconnected device', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => now()->subSeconds(15),
        ]);
        CachedProjectState::factory()->for($device)->running()->create(['project_slug' => 'reconnect']);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('reconnecting');
        });
    });

    it('transitions from reconnecting to offline after 60 seconds', function () {
        $device = DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => now()->subSeconds(61),
        ]);
        CachedProjectState::factory()->for($device)->running()->create(['project_slug' => 'timed-out']);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('offline');
        });
    });

    it('shows never-connected for device that has never been online', function () {
        DeviceAuthorization::factory()->for($this->user)->create([
            'is_online' => false,
            'last_connected_at' => null,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $devices = $page->toArray()['props']['devices'];
            expect($devices[0]['connection_status'])->toBe('never-connected');
        });
    });
});
