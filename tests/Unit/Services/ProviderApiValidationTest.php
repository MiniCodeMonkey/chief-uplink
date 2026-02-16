<?php

use App\Models\ProviderApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('hetzner api key validation succeeds with valid key', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers*' => Http::response(['servers' => []], 200),
    ]);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->post('/settings/cloud-servers', [
        'provider' => 'hetzner',
        'api_key' => 'valid-hetzner-key-1234567890',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('provider_api_keys', [
        'user_id' => $user->id,
        'provider' => 'hetzner',
    ]);
});

test('hetzner api key validation fails with invalid key', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers*' => Http::response(['error' => ['message' => 'unauthorized']], 401),
    ]);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->post('/settings/cloud-servers', [
        'provider' => 'hetzner',
        'api_key' => 'invalid-key',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseMissing('provider_api_keys', [
        'user_id' => $user->id,
        'provider' => 'hetzner',
    ]);
});

test('digitalocean api key validation succeeds with valid key', function () {
    Http::fake([
        'api.digitalocean.com/v2/account' => Http::response([
            'account' => ['name' => 'Test Account', 'email' => 'test@example.com'],
        ], 200),
    ]);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->post('/settings/cloud-servers', [
        'provider' => 'digitalocean',
        'api_key' => 'valid-do-key-1234567890',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('provider_api_keys', [
        'user_id' => $user->id,
        'provider' => 'digitalocean',
    ]);
});

test('digitalocean api key validation fails with invalid key', function () {
    Http::fake([
        'api.digitalocean.com/v2/account' => Http::response(['id' => 'unauthorized', 'message' => 'Unable to authenticate you.'], 401),
    ]);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->post('/settings/cloud-servers', [
        'provider' => 'digitalocean',
        'api_key' => 'invalid-key',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseMissing('provider_api_keys', [
        'user_id' => $user->id,
        'provider' => 'digitalocean',
    ]);
});

test('stored api key is encrypted', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers*' => Http::response(['servers' => []], 200),
    ]);

    $user = User::factory()->create();
    $apiKey = 'my-secret-api-key-for-hetzner';

    $this->actingAs($user)->post('/settings/cloud-servers', [
        'provider' => 'hetzner',
        'api_key' => $apiKey,
    ]);

    $key = ProviderApiKey::where('user_id', $user->id)->first();
    expect($key->api_key)->toBe($apiKey);

    // Verify raw DB value is encrypted
    $raw = \DB::table('provider_api_keys')->where('id', $key->id)->value('api_key');
    expect($raw)->not->toBe($apiKey);
});

test('only one api key per provider per user', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers*' => Http::response(['servers' => []], 200),
    ]);

    $user = User::factory()->create();
    ProviderApiKey::factory()->for($user)->hetzner()->create();

    $response = $this->actingAs($user)->post('/settings/cloud-servers', [
        'provider' => 'hetzner',
        'api_key' => 'another-key',
    ]);

    $response->assertRedirect();
    expect(ProviderApiKey::where('user_id', $user->id)->where('provider', 'hetzner')->count())->toBe(1);
});

test('masked key is generated on storage', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers*' => Http::response(['servers' => []], 200),
    ]);

    $user = User::factory()->create();
    $apiKey = 'a-long-api-key-for-testing-masking';

    $this->actingAs($user)->post('/settings/cloud-servers', [
        'provider' => 'hetzner',
        'api_key' => $apiKey,
    ]);

    $key = ProviderApiKey::where('user_id', $user->id)->first();
    expect($key->masked_key)->toContain('...');
});
