<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create();
});

describe('Claude Output Streaming Props', function () {
    it('provides deviceId needed for WebSocket subscription', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'streaming-project',
            'project_name' => 'Streaming Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/streaming-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Run')
            ->where('deviceId', $this->device->id)
        );
    });

    it('provides running status for output panel visibility', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'running-output',
            'stories_completed' => 2,
            'stories_total' => 5,
            'story_details' => [
                ['id' => 'US-001', 'title' => 'First story', 'status' => 'completed'],
                ['id' => 'US-002', 'title' => 'Second story', 'status' => 'completed'],
                ['id' => 'US-003', 'title' => 'Third story', 'status' => 'in_progress'],
                ['id' => 'US-004', 'title' => 'Fourth story', 'status' => 'pending'],
                ['id' => 'US-005', 'title' => 'Fifth story', 'status' => 'pending'],
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/projects/running-output/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'running')
            ->has('project.story_details', 5)
            ->where('project.stories_completed', 2)
            ->where('project.stories_total', 5)
        );
    });

    it('provides paused status showing output panel remains visible', function () {
        CachedProjectState::factory()->for($this->device)->paused()->create([
            'project_slug' => 'paused-output',
            'stories_completed' => 3,
            'stories_total' => 5,
        ]);

        $response = $this->actingAs($this->user)->get('/projects/paused-output/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'paused')
        );
    });

    it('provides error status showing output panel remains visible', function () {
        CachedProjectState::factory()->for($this->device)->error()->create([
            'project_slug' => 'error-output',
            'stories_completed' => 1,
            'stories_total' => 5,
        ]);

        $response = $this->actingAs($this->user)->get('/projects/error-output/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'error')
        );
    });

    it('provides idle status where output panel is hidden', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'idle-output',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/idle-output/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'idle')
        );
    });

    it('provides story details with in-progress story for output context', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'context-project',
            'story_details' => [
                ['id' => 'US-001', 'title' => 'Auth', 'status' => 'completed', 'iterations' => 2],
                ['id' => 'US-002', 'title' => 'Dashboard', 'status' => 'in_progress', 'iterations' => 1],
                ['id' => 'US-003', 'title' => 'Settings', 'status' => 'pending'],
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/projects/context-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('project.story_details.0.status', 'completed')
            ->where('project.story_details.1.status', 'in_progress')
            ->where('project.story_details.1.id', 'US-002')
            ->where('project.story_details.2.status', 'pending')
        );
    });
});

describe('Claude Output Streaming Access Control', function () {
    it('returns 404 for another user project', function () {
        $otherUser = User::factory()->create();
        $otherDevice = DeviceAuthorization::factory()->for($otherUser)->online()->create();
        CachedProjectState::factory()->for($otherDevice)->running()->create([
            'project_slug' => 'other-streaming',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/other-streaming/run');
        $response->assertStatus(404);
    });

    it('redirects unauthenticated users to login', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'unauth-streaming',
        ]);

        $response = $this->get('/projects/unauth-streaming/run');
        $response->assertRedirect('/login');
    });
});
