<?php

use App\Enums\CloudProvider;
use App\Enums\ServerStatus;
use App\Enums\TeamRole;
use App\Jobs\ProvisionServerJob;
use App\Models\CloudProviderCredential;
use App\Models\ManagedServer;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

// ── Create Page ─────────────────────────────────────────────────────

it('renders server create page for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/servers/create');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Servers/Create')
        ->has('credentials')
        ->has('sshKeys')
    );
});

it('passes credentials and ssh keys to create page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();

    CloudProviderCredential::factory()->count(2)->create(['team_id' => $team->id]);
    SshKey::factory()->count(3)->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->get('/servers/create');

    $response->assertInertia(fn ($page) => $page
        ->has('credentials', 2)
        ->has('sshKeys', 3)
    );
});

it('requires authentication for server create page', function () {
    $this->get('/servers/create')->assertRedirect('/login');
});

// ── Store ───────────────────────────────────────────────────────────

it('allows owner to create a managed server', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $team->id,
        'provider' => CloudProvider::Hetzner,
    ]);
    $sshKey = SshKey::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($owner)->post('/servers', [
        'credential_id' => $credential->id,
        'region_id' => 'nbg1',
        'size_id' => 'cx22',
        'name' => 'web-prod-01',
        'ssh_key_id' => $sshKey->id,
    ]);

    $response->assertRedirect('/servers');
    $response->assertSessionHas('success', 'Server is being provisioned.');

    $server = $team->managedServers()->first();
    expect($server)->not->toBeNull();
    expect($server->name)->toBe('web-prod-01');
    expect($server->provider)->toBe(CloudProvider::Hetzner);
    expect($server->status)->toBe(ServerStatus::Provisioning);
    expect($server->region_id)->toBe('nbg1');
    expect($server->size_id)->toBe('cx22');
    expect($server->credential_id)->toBe($credential->id);
    expect($server->ssh_key_id)->toBe($sshKey->id);

    Queue::assertPushed(ProvisionServerJob::class, fn ($job) => $job->server->id === $server->id);
});

it('prevents non-owner from creating a server', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $credential = CloudProviderCredential::factory()->create(['team_id' => $team->id]);
    $sshKey = SshKey::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($member)->post('/servers', [
        'credential_id' => $credential->id,
        'region_id' => 'nbg1',
        'size_id' => 'cx22',
        'name' => 'hack-server',
        'ssh_key_id' => $sshKey->id,
    ]);

    $response->assertForbidden();
    expect($team->managedServers()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('validates server creation fields', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->post('/servers', [
        'credential_id' => '',
        'region_id' => '',
        'size_id' => '',
        'name' => '',
        'ssh_key_id' => '',
    ]);

    $response->assertSessionHasErrors(['credential_id', 'region_id', 'size_id', 'name', 'ssh_key_id']);
});

it('prevents using credential from another team', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $otherOwner->currentTeam()->id,
    ]);
    $sshKey = SshKey::factory()->create(['team_id' => $owner->currentTeam()->id]);

    $response = $this->actingAs($owner)->post('/servers', [
        'credential_id' => $credential->id,
        'region_id' => 'nbg1',
        'size_id' => 'cx22',
        'name' => 'stolen-server',
        'ssh_key_id' => $sshKey->id,
    ]);

    $response->assertStatus(404);
    Queue::assertNothingPushed();
});

it('prevents using ssh key from another team', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $owner->currentTeam()->id,
    ]);
    $sshKey = SshKey::factory()->create(['team_id' => $otherOwner->currentTeam()->id]);

    $response = $this->actingAs($owner)->post('/servers', [
        'credential_id' => $credential->id,
        'region_id' => 'nbg1',
        'size_id' => 'cx22',
        'name' => 'stolen-server',
        'ssh_key_id' => $sshKey->id,
    ]);

    $response->assertStatus(404);
    Queue::assertNothingPushed();
});

// ── Regions/Sizes API ───────────────────────────────────────────────

it('prevents fetching regions for credential from another team', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $otherOwner->currentTeam()->id,
    ]);

    $response = $this->actingAs($owner)->get("/servers/credentials/{$credential->id}/regions");

    $response->assertForbidden();
});

it('prevents fetching sizes for credential from another team', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $otherOwner->currentTeam()->id,
    ]);

    $response = $this->actingAs($owner)->get("/servers/credentials/{$credential->id}/sizes");

    $response->assertForbidden();
});

// ── ManagedServer Model ─────────────────────────────────────────────

it('creates a managed server with factory', function () {
    $server = ManagedServer::factory()->create();

    expect($server->id)->not->toBeNull();
    expect($server->status)->toBe(ServerStatus::Provisioning);
    expect($server->team)->not->toBeNull();
    expect($server->credential)->not->toBeNull();
    expect($server->sshKey)->not->toBeNull();
});

it('has active and failed factory states', function () {
    $active = ManagedServer::factory()->active()->create();
    expect($active->status)->toBe(ServerStatus::Active);
    expect($active->ip_address)->not->toBeNull();
    expect($active->provider_server_id)->not->toBeNull();

    $failed = ManagedServer::factory()->failed()->create();
    expect($failed->status)->toBe(ServerStatus::Failed);
});
