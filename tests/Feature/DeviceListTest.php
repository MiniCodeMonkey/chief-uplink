<?php

use App\Models\Device;
use App\Models\Team;
use App\Models\User;

it('renders the devices index page for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/devices')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Devices/Index')
            ->has('devices', 0)
        );
});

it('requires authentication', function () {
    $this->get('/devices')
        ->assertRedirect('/login');
});

it('shows devices for the user active team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();

    $device = Device::factory()->create([
        'team_id' => $team->id,
        'name' => 'My MacBook',
    ]);

    $this->actingAs($user)
        ->get('/devices')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Devices/Index')
            ->has('devices', 1)
            ->where('devices.0.name', 'My MacBook')
            ->where('devices.0.id', $device->id)
        );
});

it('does not show devices from other teams', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();

    Device::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->get('/devices')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('devices', 0)
        );
});

it('sorts devices with online first then by last_seen_at descending', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();

    $offlineOld = Device::factory()->create([
        'team_id' => $team->id,
        'name' => 'Offline Old',
        'connected' => false,
        'last_seen_at' => now()->subHours(2),
    ]);

    $offlineRecent = Device::factory()->create([
        'team_id' => $team->id,
        'name' => 'Offline Recent',
        'connected' => false,
        'last_seen_at' => now()->subMinutes(5),
    ]);

    $online = Device::factory()->connected()->create([
        'team_id' => $team->id,
        'name' => 'Online Device',
    ]);

    $this->actingAs($user)
        ->get('/devices')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('devices', 3)
            ->where('devices.0.name', 'Online Device')
            ->where('devices.1.name', 'Offline Recent')
            ->where('devices.2.name', 'Offline Old')
        );
});

it('returns expected device fields', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();

    Device::factory()->connected()->create([
        'team_id' => $team->id,
        'name' => 'Test Device',
        'os' => 'darwin',
        'arch' => 'arm64',
        'chief_version' => '1.2.3',
    ]);

    $this->actingAs($user)
        ->get('/devices')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('devices.0', fn ($device) => $device
                ->has('id')
                ->where('name', 'Test Device')
                ->where('os', 'darwin')
                ->where('arch', 'arm64')
                ->where('chief_version', '1.2.3')
                ->where('connected', true)
                ->has('last_seen_at')
            )
        );
});

it('does not expose sensitive token fields', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();

    Device::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get('/devices')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('devices', 1)
            ->missing('devices.0.access_token')
            ->missing('devices.0.refresh_token_hash')
        );
});
