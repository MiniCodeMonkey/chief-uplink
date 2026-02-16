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

describe('PRD Creation Chat Page', function () {
    it('renders the PRD creation page with required props', function () {
        $response = $this->actingAs($this->user)->get('/projects/test-project/prd/new');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/PrdChat')
            ->where('projectSlug', 'test-project')
            ->where('projectName', 'Test Project')
            ->has('deviceId')
            ->where('mode', 'create')
        );
    });

    it('passes the correct device authorization ID as deviceId', function () {
        $response = $this->actingAs($this->user)->get('/projects/test-project/prd/new');

        $response->assertInertia(fn ($page) => $page
            ->where('deviceId', $this->device->id)
        );
    });

    it('renders for idle projects', function () {
        CachedProjectState::factory()->for($this->device)->idle()->create([
            'project_slug' => 'idle-chat',
            'project_name' => 'Idle Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/idle-chat/prd/new');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/PrdChat')
            ->where('projectSlug', 'idle-chat')
            ->where('mode', 'create')
        );
    });

    it('renders for no_prd projects', function () {
        CachedProjectState::factory()->for($this->device)->noPrd()->create([
            'project_slug' => 'noprd-chat',
            'project_name' => 'No PRD Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/noprd-chat/prd/new');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/PrdChat')
            ->where('mode', 'create')
        );
    });
});

describe('PRD Chat Access Control', function () {
    it('returns 404 for non-existent project', function () {
        $response = $this->actingAs($this->user)->get('/projects/nonexistent/prd/new');

        $response->assertStatus(404);
    });

    it('returns 404 when accessing another user project PRD chat', function () {
        $otherUser = User::factory()->create();
        $otherDevice = DeviceAuthorization::factory()->for($otherUser)->online()->create();
        CachedProjectState::factory()->for($otherDevice)->idle()->create([
            'project_slug' => 'other-chat',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/other-chat/prd/new');

        $response->assertStatus(404);
    });

    it('redirects unauthenticated users to login', function () {
        $response = $this->get('/projects/test-project/prd/new');

        $response->assertRedirect('/login');
    });

    it('allows access for revoked device projects', function () {
        $revokedDevice = DeviceAuthorization::factory()->for($this->user)->revoked()->create();
        CachedProjectState::factory()->for($revokedDevice)->idle()->create([
            'project_slug' => 'revoked-chat',
            'project_name' => 'Revoked Device Project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/revoked-chat/prd/new');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/PrdChat')
            ->where('deviceId', $revokedDevice->id)
        );
    });
});

describe('PRD Chat — Multiple Devices', function () {
    it('passes the correct deviceId when user has multiple devices', function () {
        $device2 = DeviceAuthorization::factory()->for($this->user)->online()->create();
        CachedProjectState::factory()->for($device2)->idle()->create([
            'project_slug' => 'device2-chat',
            'project_name' => 'Device 2 Project',
        ]);

        // Access project on device 2
        $response = $this->actingAs($this->user)->get('/projects/device2-chat/prd/new');

        $response->assertInertia(fn ($page) => $page
            ->where('deviceId', $device2->id)
        );

        // Access project on device 1
        $response = $this->actingAs($this->user)->get('/projects/test-project/prd/new');

        $response->assertInertia(fn ($page) => $page
            ->where('deviceId', $this->device->id)
        );
    });
});
