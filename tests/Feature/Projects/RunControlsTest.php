<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create();
});

describe('Run Controls Props', function () {
    it('provides deviceId for run command relay', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'controls-project',
            'project_name' => 'Controls Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/controls-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Run')
            ->where('deviceId', $this->device->id)
        );
    });

    it('provides project status for determining control bar state', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'running-controls',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/running-controls/run');

        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'running')
        );
    });

    it('provides idle status for showing start button', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'idle-controls',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/idle-controls/run');

        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'idle')
        );
    });

    it('provides paused status for showing resume button', function () {
        CachedProjectState::factory()->for($this->device)->paused()->create([
            'project_slug' => 'paused-controls',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/paused-controls/run');

        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'paused')
        );
    });

    it('provides error status for showing stop button', function () {
        CachedProjectState::factory()->for($this->device)->error()->create([
            'project_slug' => 'error-controls',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/error-controls/run');

        $response->assertInertia(fn ($page) => $page
            ->where('project.status', 'error')
        );
    });

    it('provides current_prd_name for run context display', function () {
        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'prd-controls',
            'current_prd_name' => 'My Feature PRD',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/prd-controls/run');

        $response->assertInertia(fn ($page) => $page
            ->where('project.current_prd_name', 'My Feature PRD')
        );
    });

    it('provides null current_prd_name for projects without PRDs', function () {
        CachedProjectState::factory()->for($this->device)->create([
            'project_slug' => 'no-prd-controls',
            'status' => 'no_prd',
            'current_prd_name' => null,
        ]);

        $response = $this->actingAs($this->user)->get('/projects/no-prd-controls/run');

        $response->assertInertia(fn ($page) => $page
            ->where('project.current_prd_name', null)
        );
    });
});

describe('Run Controls Access', function () {
    it('returns correct deviceId for projects on different devices', function () {
        $device2 = DeviceAuthorization::factory()->for($this->user)->online()->create();

        CachedProjectState::factory()->for($this->device)->running()->create([
            'project_slug' => 'device1-project',
        ]);

        CachedProjectState::factory()->for($device2)->running()->create([
            'project_slug' => 'device2-project',
        ]);

        $response1 = $this->actingAs($this->user)->get('/projects/device1-project/run');
        $response1->assertInertia(fn ($page) => $page
            ->where('deviceId', $this->device->id)
        );

        $response2 = $this->actingAs($this->user)->get('/projects/device2-project/run');
        $response2->assertInertia(fn ($page) => $page
            ->where('deviceId', $device2->id)
        );
    });

    it('returns 404 when trying to access another user project run controls', function () {
        $otherUser = User::factory()->create();
        $otherDevice = DeviceAuthorization::factory()->for($otherUser)->online()->create();
        CachedProjectState::factory()->for($otherDevice)->running()->create([
            'project_slug' => 'other-user-controls',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/other-user-controls/run');
        $response->assertStatus(404);
    });

    it('allows access to run controls for projects on revoked devices', function () {
        $revokedDevice = DeviceAuthorization::factory()->for($this->user)->revoked()->create();
        CachedProjectState::factory()->for($revokedDevice)->idle()->create([
            'project_slug' => 'revoked-controls',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/revoked-controls/run');

        // Project is still accessible even if device is revoked (just showing cached data)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('deviceId', $revokedDevice->id)
        );
    });
});
