<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\RunHistory;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create();
});

describe('Overview Tab Data', function () {
    it('renders the Overview tab with project data', function () {
        $project = CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'my-project',
            'project_name' => 'My Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/my-project');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Overview')
            ->where('projectSlug', 'my-project')
            ->where('projectName', 'My Project')
            ->has('project')
            ->has('recentRuns')
        );
    });

    it('includes full project state in the project prop', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'full-data',
            'project_name' => 'Full Data',
            'current_prd_name' => 'v1.0 Features',
            'stories_completed' => 5,
            'stories_total' => 10,
            'active_sessions' => 2,
            'git_branch' => 'main',
            'last_commit_hash' => 'abc1234',
            'last_commit_message' => 'feat: add auth',
            'recent_activity' => [
                ['event' => 'Story US-005 completed', 'timestamp' => now()->subMinutes(3)->toISOString()],
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/projects/full-data');

        $response->assertInertia(fn ($page) => $page
            ->component('projects/Overview')
            ->where('project.status', 'running')
            ->where('project.current_prd_name', 'v1.0 Features')
            ->where('project.stories_completed', 5)
            ->where('project.stories_total', 10)
            ->where('project.active_sessions', 2)
            ->where('project.git_branch', 'main')
            ->where('project.last_commit_hash', 'abc1234')
            ->where('project.last_commit_message', 'feat: add auth')
            ->has('project.recent_activity', 1)
        );
    });

    it('includes story details in the project prop', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'story-details',
            'project_name' => 'Story Details',
            'story_details' => [
                ['id' => 'US-001', 'title' => 'Story One', 'status' => 'completed'],
                ['id' => 'US-002', 'title' => 'Story Two', 'status' => 'in_progress'],
                ['id' => 'US-003', 'title' => 'Story Three', 'status' => 'pending'],
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/projects/story-details');

        $response->assertInertia(fn ($page) => $page
            ->has('project.story_details', 3)
        );
    });

    it('passes empty array for recentRuns when no history exists', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'no-runs',
            'project_name' => 'No Runs',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/no-runs');

        $response->assertInertia(fn ($page) => $page
            ->has('recentRuns', 0)
        );
    });

    it('includes recent run history sorted by most recent first', function () {
        $project = CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'with-history',
            'project_name' => 'With History',
        ]);

        // Create runs in different order to test sorting
        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'with-history',
            'prd_name' => 'Older Run',
            'started_at' => now()->subDays(5),
            'finished_at' => now()->subDays(5)->addHours(1),
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'with-history',
            'prd_name' => 'Newer Run',
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay()->addHours(1),
        ]);

        $response = $this->actingAs($this->user)->get('/projects/with-history');

        $response->assertInertia(fn ($page) => $page
            ->has('recentRuns', 2)
            ->where('recentRuns.0.prd_name', 'Newer Run')
            ->where('recentRuns.1.prd_name', 'Older Run')
        );
    });

    it('limits recent runs to 5 entries', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'many-runs',
            'project_name' => 'Many Runs',
        ]);

        // Create 7 runs
        for ($i = 0; $i < 7; $i++) {
            RunHistory::factory()->for($this->device)->completed()->create([
                'project_slug' => 'many-runs',
                'started_at' => now()->subDays($i),
                'finished_at' => now()->subDays($i)->addHour(),
            ]);
        }

        $response = $this->actingAs($this->user)->get('/projects/many-runs');

        $response->assertInertia(fn ($page) => $page
            ->has('recentRuns', 5)
        );
    });

    it('only includes runs for the same project slug', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'project-a',
            'project_name' => 'Project A',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'project-a',
            'prd_name' => 'Project A Run',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'project-b',
            'prd_name' => 'Project B Run',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/project-a');

        $response->assertInertia(fn ($page) => $page
            ->has('recentRuns', 1)
            ->where('recentRuns.0.prd_name', 'Project A Run')
        );
    });

    it('includes all run history fields in the response', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'run-fields',
            'project_name' => 'Run Fields',
        ]);

        RunHistory::factory()->for($this->device)->failed()->create([
            'project_slug' => 'run-fields',
            'prd_name' => 'Failed Run',
            'stories_completed' => 3,
            'stories_total' => 10,
            'duration_seconds' => 1800,
            'tokens_used' => 150000,
            'error_message' => 'Test suite failed',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/run-fields');

        $response->assertInertia(fn ($page) => $page
            ->has('recentRuns', 1)
            ->where('recentRuns.0.prd_name', 'Failed Run')
            ->where('recentRuns.0.status', 'failed')
            ->where('recentRuns.0.stories_completed', 3)
            ->where('recentRuns.0.stories_total', 10)
            ->where('recentRuns.0.duration_seconds', 1800)
            ->where('recentRuns.0.tokens_used', 150000)
            ->where('recentRuns.0.error_message', 'Test suite failed')
            ->has('recentRuns.0.started_at')
            ->has('recentRuns.0.finished_at')
        );
    });
});

describe('Overview Tab — Project Statuses', function () {
    it('renders correctly for idle projects', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'idle-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/idle-project');

        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'idle')
        );
    });

    it('renders correctly for error projects', function () {
        CachedProjectState::factory()->for($this->device)->error()->create([
            'project_slug' => 'error-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/error-project');

        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'error')
        );
    });

    it('renders correctly for paused projects', function () {
        CachedProjectState::factory()->for($this->device)->paused()->create([
            'project_slug' => 'paused-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/paused-project');

        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'paused')
        );
    });

    it('renders correctly for no_prd projects', function () {
        CachedProjectState::factory()->for($this->device)->noPrd()->create([
            'project_slug' => 'noprd-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/noprd-project');

        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'no_prd')
            ->where('project.current_prd_name', null)
        );
    });
});

describe('Overview Tab — Access Control', function () {
    it('does not leak run history from other devices', function () {
        $otherDevice = DeviceAuthorization::factory()->for($this->user)->online()->create();
        $project = CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'my-device-project',
        ]);

        // Create run history on the other device with the same project slug
        RunHistory::factory()->for($otherDevice)->completed()->create([
            'project_slug' => 'my-device-project',
            'prd_name' => 'Other Device Run',
        ]);

        // Create run history on the correct device
        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'my-device-project',
            'prd_name' => 'My Device Run',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/my-device-project');

        $response->assertInertia(fn ($page) => $page
            ->has('recentRuns', 1)
            ->where('recentRuns.0.prd_name', 'My Device Run')
        );
    });
});
