<?php

use App\Models\Device;
use App\Models\Team;
use App\Models\User;

it('renders the file browser page for team members', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/files/{$device->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Files/Browser')
            ->has('device')
            ->where('initialPath', '')
        );
});

it('passes the path to the file browser page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/files/{$device->id}/src/app")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Files/Browser')
            ->where('initialPath', 'src/app')
        );
});

it('requires authentication for file browser', function () {
    $device = Device::factory()->create();

    $this->get("/files/{$device->id}")
        ->assertRedirect('/login');
});

it('returns 403 for non-team members', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $device = Device::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->get("/files/{$device->id}")
        ->assertForbidden();
});

it('includes device info in the file browser', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->connected()->create([
        'team_id' => $team->id,
        'name' => 'Dev MacBook',
    ]);

    $this->actingAs($user)
        ->get("/files/{$device->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('device', fn ($d) => $d
                ->where('id', $device->id)
                ->where('name', 'Dev MacBook')
                ->where('connected', true)
                ->has('os')
            )
        );
});

it('handles deeply nested paths', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/files/{$device->id}/src/app/Http/Controllers")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('initialPath', 'src/app/Http/Controllers')
        );
});
