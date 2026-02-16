<?php

use App\Models\ProviderApiKey;
use App\Models\User;

test('provider api key belongs to user', function () {
    $user = User::factory()->create();
    $key = ProviderApiKey::factory()->for($user)->create();

    expect($key->user->id)->toBe($user->id);
});

test('provider api key encrypts api_key', function () {
    $apiKey = 'hcloud-test-api-key-1234567890';
    $key = ProviderApiKey::factory()->create(['api_key' => $apiKey]);

    // Verify decrypted value matches
    expect($key->api_key)->toBe($apiKey);

    // Verify raw database value is encrypted (not plaintext)
    $raw = \DB::table('provider_api_keys')
        ->where('id', $key->id)
        ->value('api_key');
    expect($raw)->not->toBe($apiKey);
});

test('maskKey masks short keys', function () {
    expect(ProviderApiKey::maskKey('12345678'))->toBe('********');
    expect(ProviderApiKey::maskKey('abc'))->toBe('***');
});

test('maskKey masks long keys with prefix and suffix', function () {
    $key = 'abcdefghijklmnopqrstuvwxyz';
    $masked = ProviderApiKey::maskKey($key);

    expect($masked)->toBe('abc...uvwxyz');
    expect($masked)->toContain('abc');
    expect($masked)->toContain('uvwxyz');
    expect($masked)->toContain('...');
});

test('maskKey handles 9 character key', function () {
    $masked = ProviderApiKey::maskKey('123456789');
    expect($masked)->toBe('123...456789');
});

test('provider api key factory hetzner state', function () {
    $key = ProviderApiKey::factory()->hetzner()->create();
    expect($key->provider)->toBe('hetzner');
});

test('provider api key factory digitalocean state', function () {
    $key = ProviderApiKey::factory()->digitalocean()->create();
    expect($key->provider)->toBe('digitalocean');
});
