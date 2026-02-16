<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\RunHistory;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create();
});

describe('Run Tab Data', function () {
    it('renders the run tab with project data and story details', function () {
        $project = CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'test-project',
            'project_name' => 'Test Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/test-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Run')
            ->where('projectSlug', 'test-project')
            ->where('projectName', 'Test Project')
            ->where('deviceId', $this->device->id)
            ->has('project', fn ($project) => $project
                ->has('id')
                ->has('status')
                ->has('current_prd_name')
                ->has('stories_completed')
                ->has('stories_total')
                ->has('story_details')
                ->has('tokens_used')
            )
            ->has('runHistory')
        );
    });

    it('includes story_details with status icons for running projects', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'running-project',
            'stories_completed' => 3,
            'stories_total' => 5,
            'story_details' => [
                ['id' => 'US-001', 'title' => 'First story', 'status' => 'completed'],
                ['id' => 'US-002', 'title' => 'Second story', 'status' => 'completed'],
                ['id' => 'US-003', 'title' => 'Third story', 'status' => 'completed'],
                ['id' => 'US-004', 'title' => 'Fourth story', 'status' => 'in_progress'],
                ['id' => 'US-005', 'title' => 'Fifth story', 'status' => 'pending'],
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/projects/running-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('project.stories_completed', 3)
            ->where('project.stories_total', 5)
            ->has('project.story_details', 5)
        );
    });

    it('passes null story_details for idle projects', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'idle-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/idle-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'idle')
        );
    });

    it('includes story_details with failed stories for error projects', function () {
        CachedProjectState::factory()->for($this->device)->error()->create([
            'project_slug' => 'error-project',
            'stories_completed' => 2,
            'stories_total' => 5,
            'story_details' => [
                ['id' => 'US-001', 'title' => 'First story', 'status' => 'completed'],
                ['id' => 'US-002', 'title' => 'Second story', 'status' => 'completed'],
                ['id' => 'US-003', 'title' => 'Third story', 'status' => 'failed'],
                ['id' => 'US-004', 'title' => 'Fourth story', 'status' => 'pending'],
                ['id' => 'US-005', 'title' => 'Fifth story', 'status' => 'pending'],
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/projects/error-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'error')
            ->has('project.story_details', 5)
        );
    });
});

describe('Run History', function () {
    it('includes run history sorted by most recent first', function () {
        $project = CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'history-project',
        ]);

        $olderRun = RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'history-project',
            'prd_name' => 'Older Run',
            'started_at' => now()->subDays(2),
        ]);

        $newerRun = RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'history-project',
            'prd_name' => 'Newer Run',
            'started_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)->get('/projects/history-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 2)
            ->where('runHistory.0.prd_name', 'Newer Run')
            ->where('runHistory.1.prd_name', 'Older Run')
        );
    });

    it('limits run history to 20 entries', function () {
        $project = CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'many-runs-project',
        ]);

        for ($i = 0; $i < 25; $i++) {
            RunHistory::factory()->for($this->device)->completed()->create([
                'project_slug' => 'many-runs-project',
                'started_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($this->user)->get('/projects/many-runs-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 20)
        );
    });

    it('scopes run history to the correct project slug', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'scoped-project',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'scoped-project',
            'prd_name' => 'Correct Project Run',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'other-project',
            'prd_name' => 'Other Project Run',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/scoped-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 1)
            ->where('runHistory.0.prd_name', 'Correct Project Run')
        );
    });

    it('includes all run history fields', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'fields-project',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'fields-project',
            'prd_name' => 'Test Run',
            'stories_completed' => 5,
            'stories_total' => 5,
            'duration_seconds' => 120,
            'tokens_used' => 50000,
            'error_message' => null,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($this->user)->get('/projects/fields-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory.0', fn ($run) => $run
                ->has('id')
                ->has('prd_name')
                ->has('status')
                ->has('stories_completed')
                ->has('stories_total')
                ->has('story_results')
                ->has('duration_seconds')
                ->has('tokens_used')
                ->has('error_message')
                ->has('started_at')
                ->has('finished_at')
            )
        );
    });

    it('returns empty run history when no runs exist', function () {
        CachedProjectState::factory()->for($this->device)->create([
            'project_slug' => 'no-runs-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/no-runs-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 0)
        );
    });

    it('includes run history with all status types', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'status-project',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'status-project',
            'started_at' => now()->subMinutes(4),
        ]);

        RunHistory::factory()->for($this->device)->failed()->create([
            'project_slug' => 'status-project',
            'started_at' => now()->subMinutes(3),
        ]);

        RunHistory::factory()->for($this->device)->paused()->create([
            'project_slug' => 'status-project',
            'started_at' => now()->subMinutes(2),
        ]);

        RunHistory::factory()->for($this->device)->stopped()->create([
            'project_slug' => 'status-project',
            'started_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($this->user)->get('/projects/status-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 4)
        );
    });
});

describe('Run Tab Access Control', function () {
    it('returns 404 for non-existent project', function () {
        $response = $this->actingAs($this->user)->get('/projects/nonexistent/run');
        $response->assertStatus(404);
    });

    it('returns 404 for another user project', function () {
        $otherUser = User::factory()->create();
        $otherDevice = DeviceAuthorization::factory()->for($otherUser)->online()->create();
        CachedProjectState::factory()->for($otherDevice)->running()->create([
            'project_slug' => 'other-run-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/other-run-project/run');
        $response->assertStatus(404);
    });

    it('redirects unauthenticated users to login', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'auth-test',
        ]);

        $response = $this->get('/projects/auth-test/run');
        $response->assertRedirect('/login');
    });
});

describe('Cross-Device Isolation', function () {
    it('scopes run history to the project device', function () {
        $otherDevice = DeviceAuthorization::factory()->for($this->user)->online()->create();

        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'cross-device-project',
        ]);

        // Same slug, different device — should NOT appear
        CachedProjectState::factory()->for($otherDevice)->idle()->create([
            'project_slug' => 'cross-device-project',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'cross-device-project',
            'prd_name' => 'Device 1 Run',
        ]);

        RunHistory::factory()->for($otherDevice)->completed()->create([
            'project_slug' => 'cross-device-project',
            'prd_name' => 'Device 2 Run',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/cross-device-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 1)
            ->where('runHistory.0.prd_name', 'Device 1 Run')
        );
    });
});
