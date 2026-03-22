<?php

use App\Models\CloudProviderCredential;
use App\Models\ManagedServer;
use App\Models\SshKey;
use App\Models\Team;
use App\Models\User;

it('renders the server show page for authenticated users', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();

    $credential = CloudProviderCredential::factory()->create(['team_id' => $team->id]);
    $sshKey = SshKey::factory()->create(['team_id' => $team->id]);

    $server = ManagedServer::factory()->active()->create([
        'team_id' => $team->id,
        'credential_id' => $credential->id,
        'ssh_key_id' => $sshKey->id,
    ]);

    $this->actingAs($user)
        ->get("/servers/{$server->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Servers/Show')
            ->has('server')
            ->has('isOwner')
            ->has('hasGitHubToken')
        );
});

it('requires authentication for server show', function () {
    $server = ManagedServer::factory()->create();

    $this->get("/servers/{$server->id}")
        ->assertRedirect('/login');
});

it('returns 403 for servers from other teams', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();

    $server = ManagedServer::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->get("/servers/{$server->id}")
        ->assertForbidden();
});

it('includes server details in response', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $team->id,
        'name' => 'Hetzner Prod',
    ]);
    $sshKey = SshKey::factory()->create([
        'team_id' => $team->id,
        'name' => 'deploy-key',
        'public_key' => 'ssh-ed25519 AAAAC3test',
    ]);

    $server = ManagedServer::factory()->active()->create([
        'team_id' => $team->id,
        'credential_id' => $credential->id,
        'ssh_key_id' => $sshKey->id,
        'name' => 'web-01',
        'provider' => 'hetzner',
        'region_id' => 'nbg1',
        'size_id' => 'cx22',
    ]);

    $this->actingAs($user)
        ->get("/servers/{$server->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('server.name', 'web-01')
            ->where('server.provider', 'hetzner')
            ->where('server.credential_name', 'Hetzner Prod')
            ->where('server.ssh_key.name', 'deploy-key')
            ->where('server.ssh_key.public_key', 'ssh-ed25519 AAAAC3test')
        );
});

it('includes hasGitHubToken based on user token', function () {
    $user = User::factory()->create(['github_token' => 'gho_test_token']);
    $team = $user->currentTeam();

    $credential = CloudProviderCredential::factory()->create(['team_id' => $team->id]);
    $sshKey = SshKey::factory()->create(['team_id' => $team->id]);

    $server = ManagedServer::factory()->active()->create([
        'team_id' => $team->id,
        'credential_id' => $credential->id,
        'ssh_key_id' => $sshKey->id,
    ]);

    $this->actingAs($user)
        ->get("/servers/{$server->id}")
        ->assertInertia(fn ($page) => $page
            ->where('hasGitHubToken', true)
        );
});

it('shows hasGitHubToken as false when user has no token', function () {
    $user = User::factory()->create(['github_token' => null]);
    $team = $user->currentTeam();

    $credential = CloudProviderCredential::factory()->create(['team_id' => $team->id]);
    $sshKey = SshKey::factory()->create(['team_id' => $team->id]);

    $server = ManagedServer::factory()->active()->create([
        'team_id' => $team->id,
        'credential_id' => $credential->id,
        'ssh_key_id' => $sshKey->id,
    ]);

    $this->actingAs($user)
        ->get("/servers/{$server->id}")
        ->assertInertia(fn ($page) => $page
            ->where('hasGitHubToken', false)
        );
});
