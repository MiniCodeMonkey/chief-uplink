<?php

use App\Models\CloudProviderCredential;
use App\Models\Device;
use App\Models\ManagedServer;
use App\Models\SshKey;
use App\Models\Team;
use App\Models\User;

it('renders the servers index page for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/servers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Servers/Index')
            ->has('servers', 0)
            ->where('isOwner', true)
        );
});

it('requires authentication', function () {
    $this->get('/servers')
        ->assertRedirect('/login');
});

it('shows servers for the user active team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();

    $credential = CloudProviderCredential::factory()->create(['team_id' => $team->id]);
    $sshKey = SshKey::factory()->create(['team_id' => $team->id]);

    $server = ManagedServer::factory()->active()->create([
        'team_id' => $team->id,
        'credential_id' => $credential->id,
        'ssh_key_id' => $sshKey->id,
        'name' => 'Production Server',
    ]);

    $this->actingAs($user)
        ->get('/servers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Servers/Index')
            ->has('servers', 1)
            ->where('servers.0.name', 'Production Server')
            ->where('servers.0.id', $server->id)
        );
});

it('does not show servers from other teams', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();

    ManagedServer::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->get('/servers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('servers', 0)
        );
});

it('returns expected server fields', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $team->id,
        'name' => 'Hetzner Prod',
    ]);
    $sshKey = SshKey::factory()->create(['team_id' => $team->id]);

    ManagedServer::factory()->active()->create([
        'team_id' => $team->id,
        'credential_id' => $credential->id,
        'ssh_key_id' => $sshKey->id,
        'name' => 'web-01',
        'provider' => 'hetzner',
        'region_id' => 'nbg1',
        'size_id' => 'cx22',
    ]);

    $this->actingAs($user)
        ->get('/servers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('servers.0', fn ($server) => $server
                ->has('id')
                ->where('name', 'web-01')
                ->where('status', 'active')
                ->has('ip_address')
                ->where('provider', 'hetzner')
                ->where('region_id', 'nbg1')
                ->where('size_id', 'cx22')
                ->where('credential_name', 'Hetzner Prod')
                ->has('devices')
            )
        );
});

it('includes linked device status', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();

    $credential = CloudProviderCredential::factory()->create(['team_id' => $team->id]);
    $sshKey = SshKey::factory()->create(['team_id' => $team->id]);

    $server = ManagedServer::factory()->active()->create([
        'team_id' => $team->id,
        'credential_id' => $credential->id,
        'ssh_key_id' => $sshKey->id,
    ]);

    Device::factory()->connected()->create([
        'team_id' => $team->id,
        'managed_server_id' => $server->id,
        'name' => 'Server Device',
    ]);

    $this->actingAs($user)
        ->get('/servers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('servers.0.devices', 1)
            ->where('servers.0.devices.0.name', 'Server Device')
            ->where('servers.0.devices.0.connected', true)
        );
});

it('shows isOwner as true for team owners', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/servers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('isOwner', true)
        );
});

it('shows isOwner as false for non-owner members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam();

    // Detach member from their default team and attach to the target team as member
    $member->teams()->detach();
    $member->teams()->attach($team, ['role' => 'member']);
    $member->update(['current_team_id' => $team->id]);

    $this->actingAs($member)
        ->get('/servers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('isOwner', false)
        );
});
