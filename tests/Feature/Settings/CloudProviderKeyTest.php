<?php

use App\Models\ProviderApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('cloud servers page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('cloud-servers.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/CloudServers')
        ->has('providerKeys')
        ->has('supportedProviders')
    );
});

test('cloud servers page lists provider keys', function () {
    $user = User::factory()->create();
    $key = ProviderApiKey::factory()->hetzner()->create([
        'user_id' => $user->id,
        'masked_key' => 'abc...xyz123',
        'account_name' => 'My Hetzner',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('cloud-servers.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('providerKeys', 1)
        ->where('providerKeys.0.id', $key->id)
        ->where('providerKeys.0.provider', 'hetzner')
        ->where('providerKeys.0.masked_key', 'abc...xyz123')
        ->where('providerKeys.0.account_name', 'My Hetzner')
    );
});

test('cloud servers page only shows current users keys', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    ProviderApiKey::factory()->hetzner()->create([
        'user_id' => $user->id,
    ]);
    ProviderApiKey::factory()->digitalocean()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('cloud-servers.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('providerKeys', 1)
        ->where('providerKeys.0.provider', 'hetzner')
    );
});

test('cloud servers page shows empty state when no keys', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('cloud-servers.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('providerKeys', 0)
    );
});

test('supported providers includes hetzner and digitalocean', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('cloud-servers.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('supportedProviders', ['hetzner', 'digitalocean'])
    );
});

test('user can add a valid hetzner api key', function () {
    Http::fake([
        'api.hetzner.cloud/*' => Http::response(['servers' => []], 200),
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('cloud-servers.store'), [
            'provider' => 'hetzner',
            'api_key' => 'hetzner-test-api-key-1234567890',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(ProviderApiKey::where('user_id', $user->id)->where('provider', 'hetzner')->exists())->toBeTrue();
    $key = ProviderApiKey::where('user_id', $user->id)->first();
    expect($key->account_name)->toBe('Hetzner Cloud');
    expect($key->masked_key)->not->toBe('hetzner-test-api-key-1234567890');
});

test('user can add a valid digitalocean api key', function () {
    Http::fake([
        'api.digitalocean.com/*' => Http::response([
            'account' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('cloud-servers.store'), [
            'provider' => 'digitalocean',
            'api_key' => 'digitalocean-test-api-key-12345',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $key = ProviderApiKey::where('user_id', $user->id)->first();
    expect($key->provider)->toBe('digitalocean');
    expect($key->account_name)->toBe('Test User');
});

test('adding invalid hetzner api key shows error', function () {
    Http::fake([
        'api.hetzner.cloud/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('cloud-servers.store'), [
            'provider' => 'hetzner',
            'api_key' => 'invalid-key-1234567890',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('api_key');

    expect(ProviderApiKey::where('user_id', $user->id)->exists())->toBeFalse();
});

test('adding invalid digitalocean api key shows error', function () {
    Http::fake([
        'api.digitalocean.com/*' => Http::response(['id' => 'unauthorized'], 401),
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('cloud-servers.store'), [
            'provider' => 'digitalocean',
            'api_key' => 'invalid-key-1234567890',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('api_key');

    expect(ProviderApiKey::where('user_id', $user->id)->exists())->toBeFalse();
});

test('cannot add duplicate provider key', function () {
    Http::fake([
        'api.hetzner.cloud/*' => Http::response(['servers' => []], 200),
    ]);

    $user = User::factory()->create();
    ProviderApiKey::factory()->hetzner()->create([
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('cloud-servers.store'), [
            'provider' => 'hetzner',
            'api_key' => 'another-key-1234567890',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('provider');

    expect(ProviderApiKey::where('user_id', $user->id)->count())->toBe(1);
});

test('different users can have same provider', function () {
    Http::fake([
        'api.hetzner.cloud/*' => Http::response(['servers' => []], 200),
    ]);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    ProviderApiKey::factory()->hetzner()->create([
        'user_id' => $user1->id,
    ]);

    $response = $this
        ->actingAs($user2)
        ->post(route('cloud-servers.store'), [
            'provider' => 'hetzner',
            'api_key' => 'another-key-1234567890',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('unsupported provider is rejected', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('cloud-servers.store'), [
            'provider' => 'aws',
            'api_key' => 'some-key-1234567890',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('provider');
});

test('api key must be at least 10 characters', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('cloud-servers.store'), [
            'provider' => 'hetzner',
            'api_key' => 'short',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('api_key');
});

test('api key is stored encrypted', function () {
    Http::fake([
        'api.hetzner.cloud/*' => Http::response(['servers' => []], 200),
    ]);

    $user = User::factory()->create();
    $plainKey = 'hetzner-test-api-key-1234567890';

    $this
        ->actingAs($user)
        ->post(route('cloud-servers.store'), [
            'provider' => 'hetzner',
            'api_key' => $plainKey,
        ]);

    $key = ProviderApiKey::where('user_id', $user->id)->first();

    // The encrypted cast means the raw DB value is different from the plain value
    // but accessing the attribute gives back the plain value
    expect($key->api_key)->toBe($plainKey);

    // Check the raw DB column is encrypted (different from plain text)
    $rawValue = \Illuminate\Support\Facades\DB::table('provider_api_keys')
        ->where('id', $key->id)
        ->value('api_key');
    expect($rawValue)->not->toBe($plainKey);
});

test('api key is never sent back to frontend', function () {
    $user = User::factory()->create();
    ProviderApiKey::factory()->hetzner()->create([
        'user_id' => $user->id,
        'masked_key' => 'het...abc123',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('cloud-servers.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('providerKeys', 1)
        ->where('providerKeys.0.masked_key', 'het...abc123')
        ->missing('providerKeys.0.api_key')
    );
});

test('user can remove their api key', function () {
    $user = User::factory()->create();
    $key = ProviderApiKey::factory()->hetzner()->create([
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('cloud-servers.destroy', $key->id));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(ProviderApiKey::where('id', $key->id)->exists())->toBeFalse();
});

test('user cannot remove another users api key', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $key = ProviderApiKey::factory()->hetzner()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('cloud-servers.destroy', $key->id));

    $response->assertNotFound();

    expect(ProviderApiKey::where('id', $key->id)->exists())->toBeTrue();
});

test('removing key shows success message with provider name', function () {
    $user = User::factory()->create();
    $key = ProviderApiKey::factory()->hetzner()->create([
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('cloud-servers.destroy', $key->id));

    $response->assertSessionHas('success', 'Hetzner API key removed.');
});

test('network error during validation shows appropriate error', function () {
    Http::fake([
        'api.hetzner.cloud/*' => Http::response(null, 500),
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('cloud-servers.store'), [
            'provider' => 'hetzner',
            'api_key' => 'some-valid-length-key-12345',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('api_key');
});

test('mask key hides middle characters', function () {
    expect(ProviderApiKey::maskKey('abcdefghijklmnop'))->toBe('abc...klmnop');
    expect(ProviderApiKey::maskKey('short'))->toBe('*****');
    expect(ProviderApiKey::maskKey('12345678'))->toBe('********');
    expect(ProviderApiKey::maskKey('123456789'))->toBe('123...456789');
});

test('cloud servers page requires authentication', function () {
    $response = $this->get(route('cloud-servers.index'));

    $response->assertRedirect(route('login'));
});

test('store action requires authentication', function () {
    $response = $this->post(route('cloud-servers.store'), [
        'provider' => 'hetzner',
        'api_key' => 'some-key-1234567890',
    ]);

    $response->assertRedirect(route('login'));
});

test('destroy action requires authentication', function () {
    $key = ProviderApiKey::factory()->create();

    $response = $this->delete(route('cloud-servers.destroy', $key->id));

    $response->assertRedirect(route('login'));
});

test('digitalocean validation uses account email as fallback name', function () {
    Http::fake([
        'api.digitalocean.com/*' => Http::response([
            'account' => [
                'email' => 'user@example.com',
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('cloud-servers.store'), [
            'provider' => 'digitalocean',
            'api_key' => 'digitalocean-test-api-key-12345',
        ]);

    $key = ProviderApiKey::where('user_id', $user->id)->first();
    expect($key->account_name)->toBe('user@example.com');
});
