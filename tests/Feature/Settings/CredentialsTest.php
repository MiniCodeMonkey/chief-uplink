<?php

use App\Enums\CloudProvider;
use App\Enums\TeamRole;
use App\Models\CloudProviderCredential;
use App\Models\SshKey;
use App\Models\User;

// ── Page ─────────────────────────────────────────────────────────────

it('renders credentials settings page for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings/credentials');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Settings/Credentials')
        ->has('credentials')
        ->has('sshKeys')
        ->has('isOwner')
    );
});

it('shows isOwner as false for non-owner members', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($member)->get('/settings/credentials');

    $response->assertInertia(fn ($page) => $page
        ->where('isOwner', false)
    );
});

it('requires authentication to access credentials settings', function () {
    $response = $this->get('/settings/credentials');

    $response->assertRedirect('/login');
});

// ── Cloud Provider Credentials ───────────────────────────────────────

it('allows owner to create a cloud provider credential', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $response = $this->actingAs($owner)->post('/settings/credentials', [
        'name' => 'My Hetzner Key',
        'provider' => 'hetzner',
        'api_key' => 'secret-api-key-123',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Cloud provider credential added.');

    $credential = $team->cloudProviderCredentials()->first();
    expect($credential)->not->toBeNull();
    expect($credential->name)->toBe('My Hetzner Key');
    expect($credential->provider)->toBe(CloudProvider::Hetzner);
    expect($credential->api_key)->toBe('secret-api-key-123');
});

it('prevents non-owner from creating a cloud provider credential', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($member)->post('/settings/credentials', [
        'name' => 'Hacked Key',
        'provider' => 'hetzner',
        'api_key' => 'hacked-key',
    ]);

    $response->assertForbidden();
    expect($team->cloudProviderCredentials()->count())->toBe(0);
});

it('validates cloud provider credential fields', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->post('/settings/credentials', [
        'name' => '',
        'provider' => 'invalid',
        'api_key' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'provider', 'api_key']);
});

it('allows owner to update a cloud provider credential', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $team->id,
        'name' => 'Old Name',
        'provider' => CloudProvider::Hetzner,
    ]);

    $response = $this->actingAs($owner)->put("/settings/credentials/{$credential->id}", [
        'name' => 'New Name',
        'provider' => 'digitalocean',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Cloud provider credential updated.');

    $credential->refresh();
    expect($credential->name)->toBe('New Name');
    expect($credential->provider)->toBe(CloudProvider::DigitalOcean);
});

it('keeps existing api_key when update does not include one', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $team->id,
        'api_key' => 'original-key',
    ]);

    $this->actingAs($owner)->put("/settings/credentials/{$credential->id}", [
        'name' => $credential->name,
        'provider' => $credential->provider->value,
    ]);

    expect($credential->fresh()->api_key)->toBe('original-key');
});

it('prevents non-owner from updating a cloud provider credential', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this->actingAs($member)->put("/settings/credentials/{$credential->id}", [
        'name' => 'Hacked',
        'provider' => 'hetzner',
    ]);

    $response->assertForbidden();
});

it('prevents updating credential from another team', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $otherOwner->currentTeam()->id,
    ]);

    $response = $this->actingAs($owner)->put("/settings/credentials/{$credential->id}", [
        'name' => 'Stolen',
        'provider' => 'hetzner',
    ]);

    $response->assertForbidden();
});

it('allows owner to delete a cloud provider credential', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this->actingAs($owner)->delete("/settings/credentials/{$credential->id}");

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Cloud provider credential deleted.');
    expect(CloudProviderCredential::find($credential->id))->toBeNull();
});

it('prevents non-owner from deleting a cloud provider credential', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this->actingAs($member)->delete("/settings/credentials/{$credential->id}");

    $response->assertForbidden();
    expect(CloudProviderCredential::find($credential->id))->not->toBeNull();
});

it('stores api key encrypted at rest', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $credential = CloudProviderCredential::factory()->create([
        'team_id' => $team->id,
        'api_key' => 'my-secret-key',
    ]);

    // The raw database value should not equal the plain text
    $rawValue = DB::table('cloud_provider_credentials')
        ->where('id', $credential->id)
        ->value('api_key');

    expect($rawValue)->not->toBe('my-secret-key');
    expect($credential->fresh()->api_key)->toBe('my-secret-key');
});

// ── SSH Keys ─────────────────────────────────────────────────────────

it('allows owner to create an ssh key', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $response = $this->actingAs($owner)->post('/settings/ssh-keys', [
        'name' => 'My Deploy Key',
        'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAItest user@host',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'SSH key added.');

    $key = $team->sshKeys()->first();
    expect($key)->not->toBeNull();
    expect($key->name)->toBe('My Deploy Key');
    expect($key->public_key)->toBe('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAItest user@host');
});

it('prevents non-owner from creating an ssh key', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($member)->post('/settings/ssh-keys', [
        'name' => 'Hacked Key',
        'public_key' => 'ssh-rsa AAAA hacked@host',
    ]);

    $response->assertForbidden();
});

it('validates ssh key fields', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->post('/settings/ssh-keys', [
        'name' => '',
        'public_key' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'public_key']);
});

it('allows owner to update an ssh key', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $key = SshKey::factory()->create([
        'team_id' => $team->id,
        'name' => 'Old Name',
    ]);

    $response = $this->actingAs($owner)->put("/settings/ssh-keys/{$key->id}", [
        'name' => 'New Name',
        'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAInew user@host',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'SSH key updated.');

    $key->refresh();
    expect($key->name)->toBe('New Name');
});

it('prevents non-owner from updating an ssh key', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $key = SshKey::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this->actingAs($member)->put("/settings/ssh-keys/{$key->id}", [
        'name' => 'Hacked',
        'public_key' => 'ssh-rsa hacked',
    ]);

    $response->assertForbidden();
});

it('prevents updating ssh key from another team', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $key = SshKey::factory()->create([
        'team_id' => $otherOwner->currentTeam()->id,
    ]);

    $response = $this->actingAs($owner)->put("/settings/ssh-keys/{$key->id}", [
        'name' => 'Stolen',
        'public_key' => 'ssh-rsa stolen',
    ]);

    $response->assertForbidden();
});

it('allows owner to delete an ssh key', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $key = SshKey::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this->actingAs($owner)->delete("/settings/ssh-keys/{$key->id}");

    $response->assertRedirect();
    $response->assertSessionHas('success', 'SSH key deleted.');
    expect(SshKey::find($key->id))->toBeNull();
});

it('prevents non-owner from deleting an ssh key', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member, ['role' => TeamRole::Member->value]);

    $key = SshKey::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this->actingAs($member)->delete("/settings/ssh-keys/{$key->id}");

    $response->assertForbidden();
    expect(SshKey::find($key->id))->not->toBeNull();
});

it('lists credentials and ssh keys on the page', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    CloudProviderCredential::factory()->count(2)->create(['team_id' => $team->id]);
    SshKey::factory()->count(3)->create(['team_id' => $team->id]);

    $response = $this->actingAs($owner)->get('/settings/credentials');

    $response->assertInertia(fn ($page) => $page
        ->has('credentials', 2)
        ->has('sshKeys', 3)
        ->where('isOwner', true)
    );
});
