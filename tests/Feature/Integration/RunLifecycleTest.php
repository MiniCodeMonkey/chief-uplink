<?php

use App\Events\ChiefMessageReceived;
use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\RunHistory;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'test-device',
    ]);
    $this->project = CachedProjectState::factory()->for($this->device)->idle()->create([
        'project_slug' => 'lifecycle-project',
        'project_name' => 'Lifecycle Project',
    ]);
});

/*
|--------------------------------------------------------------------------
| Start Run
|--------------------------------------------------------------------------
*/

describe('Start Run', function () {
    it('sends start_run command to chief server', function () {
        Event::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'start_run',
                'payload' => [
                    'project_slug' => 'lifecycle-project',
                    'prd_id' => 'main',
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'sent',
                'type' => 'start_run',
                'device_id' => $this->device->id,
            ]);
    });

    it('cannot start run when server is offline', function () {
        $this->device->update(['is_online' => false]);

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'start_run',
                'payload' => ['project_slug' => 'lifecycle-project'],
            ]);

        $response->assertStatus(503)
            ->assertJson([
                'error' => 'server_offline',
                'message' => 'Server offline',
            ]);
    });
});

/*
|--------------------------------------------------------------------------
| Pause Run
|--------------------------------------------------------------------------
*/

describe('Pause Run', function () {
    it('sends pause_run command to chief server', function () {
        Event::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'pause_run',
                'payload' => ['project_slug' => 'lifecycle-project'],
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'sent',
                'type' => 'pause_run',
            ]);
    });
});

/*
|--------------------------------------------------------------------------
| Resume Run
|--------------------------------------------------------------------------
*/

describe('Resume Run', function () {
    it('sends resume_run command to chief server', function () {
        Event::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'resume_run',
                'payload' => ['project_slug' => 'lifecycle-project'],
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'sent',
                'type' => 'resume_run',
            ]);
    });
});

/*
|--------------------------------------------------------------------------
| Stop Run
|--------------------------------------------------------------------------
*/

describe('Stop Run', function () {
    it('sends stop_run command to chief server', function () {
        Event::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/ws/command/{$this->device->id}", [
                'type' => 'stop_run',
                'payload' => ['project_slug' => 'lifecycle-project'],
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'sent',
                'type' => 'stop_run',
            ]);
    });
});

/*
|--------------------------------------------------------------------------
| Run Progress Events (Chief → Browser)
|--------------------------------------------------------------------------
*/

describe('Run Progress Events', function () {
    it('broadcasts run_progress event with story details', function () {
        Event::fake([ChiefMessageReceived::class]);

        $message = [
            'type' => 'run_progress',
            'payload' => [
                'project_slug' => 'lifecycle-project',
                'story_id' => 'US-001',
                'story_title' => 'User Authentication',
                'status' => 'in_progress',
                'stories_completed' => 2,
                'stories_total' => 5,
                'iteration' => 1,
            ],
        ];

        ChiefMessageReceived::dispatch(
            $this->device->id,
            $this->user->id,
            $message,
        );

        Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
            return $event->message['type'] === 'run_progress'
                && $event->message['payload']['story_id'] === 'US-001'
                && $event->message['payload']['status'] === 'in_progress';
        });
    });

    it('broadcasts run_complete event', function () {
        Event::fake([ChiefMessageReceived::class]);

        $message = [
            'type' => 'run_complete',
            'payload' => [
                'project_slug' => 'lifecycle-project',
                'status' => 'completed',
                'stories_completed' => 5,
                'stories_total' => 5,
            ],
        ];

        ChiefMessageReceived::dispatch(
            $this->device->id,
            $this->user->id,
            $message,
        );

        Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
            return $event->message['type'] === 'run_complete'
                && $event->message['payload']['status'] === 'completed';
        });
    });

    it('broadcasts run_complete event with failed status', function () {
        Event::fake([ChiefMessageReceived::class]);

        $message = [
            'type' => 'run_complete',
            'payload' => [
                'project_slug' => 'lifecycle-project',
                'status' => 'failed',
                'stories_completed' => 3,
                'stories_total' => 5,
                'error_message' => 'Story US-004 failed after 3 iterations',
            ],
        ];

        ChiefMessageReceived::dispatch(
            $this->device->id,
            $this->user->id,
            $message,
        );

        Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
            return $event->message['type'] === 'run_complete'
                && $event->message['payload']['status'] === 'failed'
                && $event->message['payload']['error_message'] !== null;
        });
    });

    it('broadcasts run_paused event', function () {
        Event::fake([ChiefMessageReceived::class]);

        $message = [
            'type' => 'run_paused',
            'payload' => [
                'project_slug' => 'lifecycle-project',
                'reason' => 'quota_exhausted',
                'stories_completed' => 2,
                'stories_total' => 5,
            ],
        ];

        ChiefMessageReceived::dispatch(
            $this->device->id,
            $this->user->id,
            $message,
        );

        Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
            return $event->message['type'] === 'run_paused'
                && $event->message['payload']['reason'] === 'quota_exhausted';
        });
    });
});

/*
|--------------------------------------------------------------------------
| Run History
|--------------------------------------------------------------------------
*/

describe('Run History', function () {
    it('renders run history on run tab', function () {
        RunHistory::factory()->completed()->create([
            'device_authorization_id' => $this->device->id,
            'project_slug' => 'lifecycle-project',
            'prd_name' => 'Feature PRD',
            'stories_completed' => 5,
            'stories_total' => 5,
        ]);

        RunHistory::factory()->failed()->create([
            'device_authorization_id' => $this->device->id,
            'project_slug' => 'lifecycle-project',
            'prd_name' => 'Bug Fix PRD',
            'stories_completed' => 2,
            'stories_total' => 4,
        ]);

        $response = $this->actingAs($this->user)->get('/projects/lifecycle-project/run');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Run')
            ->has('runHistory', 2)
        );
    });

    it('renders run history with story results', function () {
        RunHistory::factory()->completed()->create([
            'device_authorization_id' => $this->device->id,
            'project_slug' => 'lifecycle-project',
            'story_results' => [
                ['id' => 'US-001', 'title' => 'Login', 'status' => 'completed', 'iterations' => 1],
                ['id' => 'US-002', 'title' => 'Register', 'status' => 'completed', 'iterations' => 2],
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/projects/lifecycle-project/run');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $history = $page->toArray()['props']['runHistory'];
            expect($history[0]['story_results'])->toHaveCount(2);
            expect($history[0]['story_results'][0]['status'])->toBe('completed');
        });
    });

    it('sorts run history by most recent first', function () {
        RunHistory::factory()->completed()->create([
            'device_authorization_id' => $this->device->id,
            'project_slug' => 'lifecycle-project',
            'prd_name' => 'Older Run',
            'started_at' => now()->subDays(2),
        ]);

        RunHistory::factory()->completed()->create([
            'device_authorization_id' => $this->device->id,
            'project_slug' => 'lifecycle-project',
            'prd_name' => 'Newer Run',
            'started_at' => now()->subHours(1),
        ]);

        $response = $this->actingAs($this->user)->get('/projects/lifecycle-project/run');

        $response->assertInertia(function ($page) {
            $history = $page->toArray()['props']['runHistory'];
            expect($history[0]['prd_name'])->toBe('Newer Run');
            expect($history[1]['prd_name'])->toBe('Older Run');
        });
    });

    it('shows run history even when server is offline', function () {
        $this->device->update(['is_online' => false, 'last_connected_at' => now()->subHours(1)]);

        RunHistory::factory()->completed()->create([
            'device_authorization_id' => $this->device->id,
            'project_slug' => 'lifecycle-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/lifecycle-project/run');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 1)
        );
    });

    it('shows empty state when no runs exist', function () {
        $response = $this->actingAs($this->user)->get('/projects/lifecycle-project/run');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 0)
        );
    });
});

/*
|--------------------------------------------------------------------------
| Run Tab with Story Details
|--------------------------------------------------------------------------
*/

describe('Run Tab with Story Details', function () {
    it('shows story details for a running project', function () {
        $this->project->update([
            'status' => 'running',
            'story_details' => [
                ['id' => 'US-001', 'title' => 'Auth', 'status' => 'completed', 'iterations' => 1],
                ['id' => 'US-002', 'title' => 'Dashboard', 'status' => 'in_progress', 'iterations' => 2],
                ['id' => 'US-003', 'title' => 'Settings', 'status' => 'pending'],
            ],
            'stories_completed' => 1,
            'stories_total' => 3,
        ]);

        $response = $this->actingAs($this->user)->get('/projects/lifecycle-project/run');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $project = $page->toArray()['props']['project'];
            expect($project['status'])->toBe('running');
            expect($project['story_details'])->toHaveCount(3);
            expect($project['stories_completed'])->toBe(1);
            expect($project['stories_total'])->toBe(3);
        });
    });

    it('shows failed story with error summary', function () {
        $this->project->update([
            'status' => 'error',
            'story_details' => [
                ['id' => 'US-001', 'title' => 'Auth', 'status' => 'completed', 'iterations' => 1],
                ['id' => 'US-002', 'title' => 'Dashboard', 'status' => 'failed', 'error_summary' => 'Test failures after 3 iterations'],
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/projects/lifecycle-project/run');

        $response->assertInertia(function ($page) {
            $project = $page->toArray()['props']['project'];
            $failedStory = collect($project['story_details'])->firstWhere('status', 'failed');
            expect($failedStory['error_summary'])->toBe('Test failures after 3 iterations');
        });
    });
});
