<?php

use App\Models\CloudProviderCredential;
use App\Models\ManagedServer;
use App\Models\SshKey;
use App\Models\Team;
use App\Models\User;
use App\Services\GitHubKeyService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

// ── GitHubKeyService ─────────────────────────────────────────────────

it('adds an SSH key to GitHub via the API', function () {
    Http::fake([
        'api.github.com/user/keys' => Http::response(['id' => 123, 'key' => 'ssh-ed25519 AAAA'], 201),
    ]);

    $sshKey = SshKey::factory()->create([
        'name' => 'deploy-key',
        'public_key' => 'ssh-ed25519 AAAA',
    ]);

    $service = new GitHubKeyService;
    $result = $service->addKeyToGitHub('gho_test_token', $sshKey);

    expect($result)->toHaveKey('id', 123);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/user/keys'
            && $request->hasHeader('Authorization', 'Bearer gho_test_token')
            && $request['title'] === 'chief-uplink: deploy-key'
            && $request['key'] === 'ssh-ed25519 AAAA';
    });
});

it('throws on GitHub API error', function () {
    Http::fake([
        'api.github.com/user/keys' => Http::response(['message' => 'Bad credentials'], 401),
    ]);

    $sshKey = SshKey::factory()->create();

    $service = new GitHubKeyService;
    $service->addKeyToGitHub('bad_token', $sshKey);
})->throws(RequestException::class);

it('checks if token has admin:public_key scope', function () {
    Http::fake([
        'api.github.com/user' => Http::response([], 200, ['X-OAuth-Scopes' => 'read:user, user:email, admin:public_key']),
    ]);

    $service = new GitHubKeyService;

    expect($service->hasPublicKeyScope('gho_test_token'))->toBeTrue();
});

it('returns false when token lacks admin:public_key scope', function () {
    Http::fake([
        'api.github.com/user' => Http::response([], 200, ['X-OAuth-Scopes' => 'read:user, user:email']),
    ]);

    $service = new GitHubKeyService;

    expect($service->hasPublicKeyScope('gho_test_token'))->toBeFalse();
});

// ── GitHubKeyController ─────────────────────────────────────────────

it('returns 404 for non-existent server', function () {
    $user = User::factory()->create(['github_token' => 'gho_valid_token']);

    $this->actingAs($user)
        ->post('/servers/99999/deploy-key')
        ->assertNotFound();
});

it('adds deploy key via controller with valid scope', function () {
    Http::fake([
        'api.github.com/user' => Http::response([], 200, ['X-OAuth-Scopes' => 'read:user, admin:public_key']),
        'api.github.com/user/keys' => Http::response(['id' => 456], 201),
    ]);

    $user = User::factory()->create(['github_token' => 'gho_valid_token']);
    $team = $user->currentTeam();

    $credential = CloudProviderCredential::factory()->create(['team_id' => $team->id]);
    $sshKey = SshKey::factory()->create(['team_id' => $team->id]);

    $server = ManagedServer::factory()->active()->create([
        'team_id' => $team->id,
        'credential_id' => $credential->id,
        'ssh_key_id' => $sshKey->id,
    ]);

    $this->actingAs($user)
        ->post("/servers/{$server->id}/deploy-key")
        ->assertRedirect()
        ->assertSessionHas('success', 'SSH key added to GitHub successfully.');
});

it('redirects to GitHub auth when token lacks scope', function () {
    Http::fake([
        'api.github.com/user' => Http::response([], 200, ['X-OAuth-Scopes' => 'read:user, user:email']),
    ]);

    $user = User::factory()->create(['github_token' => 'gho_limited_token']);
    $team = $user->currentTeam();

    $credential = CloudProviderCredential::factory()->create(['team_id' => $team->id]);
    $sshKey = SshKey::factory()->create(['team_id' => $team->id]);

    $server = ManagedServer::factory()->active()->create([
        'team_id' => $team->id,
        'credential_id' => $credential->id,
        'ssh_key_id' => $sshKey->id,
    ]);

    $this->actingAs($user)
        ->post("/servers/{$server->id}/deploy-key")
        ->assertRedirect(route('github.keys.authorize'));

    expect(session('github_key_ssh_key_id'))->toBe($sshKey->id);
});

it('returns error when user has no GitHub token', function () {
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
        ->post("/servers/{$server->id}/deploy-key")
        ->assertRedirect()
        ->assertSessionHas('error', 'GitHub account not connected. Please log in with GitHub first.');
});

it('prevents adding deploy key for other teams ssh keys', function () {
    $user = User::factory()->create(['github_token' => 'gho_token']);
    $otherTeam = Team::factory()->create();

    $credential = CloudProviderCredential::factory()->create(['team_id' => $otherTeam->id]);
    $sshKey = SshKey::factory()->create(['team_id' => $otherTeam->id]);

    $server = ManagedServer::factory()->active()->create([
        'team_id' => $otherTeam->id,
        'credential_id' => $credential->id,
        'ssh_key_id' => $sshKey->id,
    ]);

    $this->actingAs($user)
        ->post("/servers/{$server->id}/deploy-key")
        ->assertForbidden();
});

it('handles GitHub 422 duplicate key error', function () {
    Http::fake([
        'api.github.com/user' => Http::response([], 200, ['X-OAuth-Scopes' => 'read:user, admin:public_key']),
        'api.github.com/user/keys' => Http::response(['message' => 'key is already in use'], 422),
    ]);

    $user = User::factory()->create(['github_token' => 'gho_valid_token']);
    $team = $user->currentTeam();

    $credential = CloudProviderCredential::factory()->create(['team_id' => $team->id]);
    $sshKey = SshKey::factory()->create(['team_id' => $team->id]);

    $server = ManagedServer::factory()->active()->create([
        'team_id' => $team->id,
        'credential_id' => $credential->id,
        'ssh_key_id' => $sshKey->id,
    ]);

    $this->actingAs($user)
        ->post("/servers/{$server->id}/deploy-key")
        ->assertRedirect()
        ->assertSessionHas('error', 'This key already exists on your GitHub account.');
});

it('requires authentication for deploy key route', function () {
    $server = ManagedServer::factory()->create();

    $this->post("/servers/{$server->id}/deploy-key")
        ->assertRedirect('/login');
});
