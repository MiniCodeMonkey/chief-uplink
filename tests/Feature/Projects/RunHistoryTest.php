<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\RunHistory;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create();
});

describe('Run History Display', function () {
    it('shows run history entries with all required fields', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'history-display-project',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'history-display-project',
            'prd_name' => 'My PRD',
            'stories_completed' => 8,
            'stories_total' => 10,
            'duration_seconds' => 3600,
            'tokens_used' => 125000,
            'error_message' => null,
            'started_at' => now()->subHours(2),
            'finished_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->user)->get('/projects/history-display-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 1)
            ->has('runHistory.0', fn ($run) => $run
                ->has('id')
                ->where('prd_name', 'My PRD')
                ->where('status', 'completed')
                ->where('stories_completed', 8)
                ->where('stories_total', 10)
                ->where('duration_seconds', 3600)
                ->where('tokens_used', 125000)
                ->where('error_message', null)
                ->has('started_at')
                ->has('finished_at')
                ->has('story_results')
            )
        );
    });

    it('shows completed runs with green status styling', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'status-color-project',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'status-color-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/status-color-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('runHistory.0.status', 'completed')
        );
    });

    it('shows failed runs with error message and story results', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'failed-run-project',
        ]);

        RunHistory::factory()->for($this->device)->failed()->create([
            'project_slug' => 'failed-run-project',
            'error_message' => 'Test command failed with exit code 1',
            'story_results' => [
                ['id' => 'US-001', 'title' => 'First story', 'status' => 'completed'],
                ['id' => 'US-002', 'title' => 'Second story', 'status' => 'failed'],
                ['id' => 'US-003', 'title' => 'Third story', 'status' => 'pending'],
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/projects/failed-run-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('runHistory.0.status', 'failed')
            ->where('runHistory.0.error_message', 'Test command failed with exit code 1')
            ->has('runHistory.0.story_results', 3)
        );
    });

    it('shows paused and stopped runs with correct status', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'mixed-status-project',
        ]);

        RunHistory::factory()->for($this->device)->paused()->create([
            'project_slug' => 'mixed-status-project',
            'started_at' => now()->subMinutes(2),
        ]);

        RunHistory::factory()->for($this->device)->stopped()->create([
            'project_slug' => 'mixed-status-project',
            'started_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($this->user)->get('/projects/mixed-status-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 2)
            ->where('runHistory.0.status', 'stopped')
            ->where('runHistory.1.status', 'paused')
        );
    });
});

describe('Run History Data Source', function () {
    it('serves run history from database even when server is offline', function () {
        $offlineDevice = DeviceAuthorization::factory()->for($this->user)->offline()->create();

        CachedProjectState::factory()->for($offlineDevice)->idle()->create([
            'project_slug' => 'offline-history-project',
        ]);

        RunHistory::factory()->for($offlineDevice)->completed()->create([
            'project_slug' => 'offline-history-project',
            'prd_name' => 'Offline Run',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/offline-history-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 1)
            ->where('runHistory.0.prd_name', 'Offline Run')
        );
    });
});

describe('Run History in Overview', function () {
    it('shows recent runs in overview tab limited to 5', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'overview-history-project',
        ]);

        for ($i = 0; $i < 8; $i++) {
            RunHistory::factory()->for($this->device)->completed()->create([
                'project_slug' => 'overview-history-project',
                'started_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($this->user)->get('/projects/overview-history-project');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('recentRuns', 5)
        );
    });

    it('shows empty state message when no runs exist on overview', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'no-runs-overview-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/no-runs-overview-project');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('recentRuns', 0)
        );
    });
});

describe('Run History Sorting and Scoping', function () {
    it('sorts run history by most recent first', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'sort-test-project',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'sort-test-project',
            'prd_name' => 'Oldest',
            'started_at' => now()->subDays(3),
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'sort-test-project',
            'prd_name' => 'Middle',
            'started_at' => now()->subDays(2),
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'sort-test-project',
            'prd_name' => 'Newest',
            'started_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)->get('/projects/sort-test-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 3)
            ->where('runHistory.0.prd_name', 'Newest')
            ->where('runHistory.1.prd_name', 'Middle')
            ->where('runHistory.2.prd_name', 'Oldest')
        );
    });

    it('does not show runs from other projects', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'my-scoped-project',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'my-scoped-project',
            'prd_name' => 'My Run',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'other-scoped-project',
            'prd_name' => 'Other Run',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/my-scoped-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 1)
            ->where('runHistory.0.prd_name', 'My Run')
        );
    });

    it('does not show runs from other devices with same project slug', function () {
        $otherDevice = DeviceAuthorization::factory()->for($this->user)->online()->create();

        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'device-scoped-project',
        ]);

        CachedProjectState::factory()->for($otherDevice)->idle()->create([
            'project_slug' => 'device-scoped-project',
        ]);

        RunHistory::factory()->for($this->device)->completed()->create([
            'project_slug' => 'device-scoped-project',
            'prd_name' => 'Device 1 Run',
        ]);

        RunHistory::factory()->for($otherDevice)->completed()->create([
            'project_slug' => 'device-scoped-project',
            'prd_name' => 'Device 2 Run',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/device-scoped-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('runHistory', 1)
            ->where('runHistory.0.prd_name', 'Device 1 Run')
        );
    });
});

describe('Run History Access Control', function () {
    it('prevents access to other users run history', function () {
        $otherUser = User::factory()->create();
        $otherDevice = DeviceAuthorization::factory()->for($otherUser)->online()->create();

        CachedProjectState::factory()->for($otherDevice)->idle()->create([
            'project_slug' => 'other-user-history-project',
        ]);

        RunHistory::factory()->for($otherDevice)->completed()->create([
            'project_slug' => 'other-user-history-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/other-user-history-project/run');

        $response->assertStatus(404);
    });

    it('requires authentication to view run history', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'auth-history-project',
        ]);

        $response = $this->get('/projects/auth-history-project/run');

        $response->assertRedirect('/login');
    });
});
