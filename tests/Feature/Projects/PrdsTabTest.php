<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create();
    $this->project = CachedProjectState::factory()->for($this->device)->running()->create([
        'project_name' => 'Test Project',
        'project_slug' => 'test-project',
    ]);
});

describe('PRDs Tab Rendering', function () {
    it('renders the PRDs tab with required props', function () {
        $response = $this->actingAs($this->user)->get('/projects/test-project/prds');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Prds')
            ->where('projectSlug', 'test-project')
            ->where('projectName', 'Test Project')
            ->has('deviceId')
        );
    });

    it('passes the correct device authorization ID as deviceId', function () {
        $response = $this->actingAs($this->user)->get('/projects/test-project/prds');

        $response->assertInertia(fn ($page) => $page
            ->where('deviceId', $this->device->id)
        );
    });

    it('renders for idle projects', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'idle-prds',
            'project_name' => 'Idle Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/idle-prds/prds');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Prds')
            ->where('projectSlug', 'idle-prds')
            ->where('deviceId', $this->device->id)
        );
    });

    it('renders for no_prd projects', function () {
        CachedProjectState::factory()->for($this->device)->noPrd()->create([
            'project_slug' => 'noprd-prds',
            'project_name' => 'No PRD Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/noprd-prds/prds');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Prds')
            ->where('projectSlug', 'noprd-prds')
            ->where('deviceId', $this->device->id)
        );
    });

    it('renders for error projects', function () {
        CachedProjectState::factory()->for($this->device)->error()->create([
            'project_slug' => 'error-prds',
            'project_name' => 'Error Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/error-prds/prds');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Prds')
            ->where('deviceId', $this->device->id)
        );
    });

    it('renders for paused projects', function () {
        CachedProjectState::factory()->for($this->device)->paused()->create([
            'project_slug' => 'paused-prds',
            'project_name' => 'Paused Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/paused-prds/prds');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Prds')
            ->where('projectSlug', 'paused-prds')
            ->where('deviceId', $this->device->id)
        );
    });
});

describe('PRDs Tab Access Control', function () {
    it('returns 404 for non-existent project', function () {
        $response = $this->actingAs($this->user)->get('/projects/nonexistent/prds');

        $response->assertStatus(404);
    });

    it('returns 404 when accessing another user project PRDs', function () {
        $otherUser = User::factory()->create();
        $otherDevice = DeviceAuthorization::factory()->for($otherUser)->online()->create();
        CachedProjectState::factory()->for($otherDevice)->idle()->create([
            'project_slug' => 'other-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/other-project/prds');

        $response->assertStatus(404);
    });

    it('redirects unauthenticated users to login', function () {
        $response = $this->get('/projects/test-project/prds');

        $response->assertRedirect('/login');
    });

    it('allows access to PRDs for revoked device projects', function () {
        $revokedDevice = DeviceAuthorization::factory()->for($this->user)->revoked()->create();
        CachedProjectState::factory()->for($revokedDevice)->idle()->create([
            'project_slug' => 'revoked-prds',
            'project_name' => 'Revoked Device Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/revoked-prds/prds');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Prds')
            ->where('deviceId', $revokedDevice->id)
        );
    });
});

describe('PRDs Tab — Multiple Devices', function () {
    it('passes the correct deviceId when user has multiple devices', function () {
        $device2 = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device2)->idle()->create([
            'project_slug' => 'device2-project',
            'project_name' => 'Device 2 Project',
        ]);

        // Access project on device 2
        $response = $this->actingAs($this->user)->get('/projects/device2-project/prds');

        $response->assertInertia(fn ($page) => $page
            ->where('deviceId', $device2->id)
        );

        // Access project on device 1
        $response = $this->actingAs($this->user)->get('/projects/test-project/prds');

        $response->assertInertia(fn ($page) => $page
            ->where('deviceId', $this->device->id)
        );
    });
});
