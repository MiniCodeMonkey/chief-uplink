<?php

use App\Models\Device;
use Illuminate\Http\Request;

beforeEach(function () {
    // Register a test route that uses the auth.device middleware
    Route::get('/test/device-auth', function () {
        return response()->json(['ok' => true]);
    })->middleware('auth.device');
});

it('rejects requests without a bearer token', function () {
    $this->getJson('/test/device-auth')
        ->assertUnauthorized()
        ->assertJson(['error' => 'missing_token']);
});

it('rejects requests with an invalid token', function () {
    $this->getJson('/test/device-auth', ['Authorization' => 'Bearer invalid-token'])
        ->assertUnauthorized()
        ->assertJson(['error' => 'invalid_token']);
});

it('rejects requests with an expired token', function () {
    $device = Device::factory()->create([
        'token_expires_at' => now()->subDay(),
    ]);

    // We need the plain token to authenticate, but the factory stores the hash.
    // Create a device with a known token.
    $plainToken = 'test-token-'.fake()->uuid();
    $device->update(['access_token' => hash('sha256', $plainToken)]);

    $this->getJson('/test/device-auth', ['Authorization' => "Bearer {$plainToken}"])
        ->assertUnauthorized()
        ->assertJson(['error' => 'expired_token']);
});

it('allows requests with a valid token', function () {
    $plainToken = 'valid-token-'.fake()->uuid();
    Device::factory()->create([
        'access_token' => hash('sha256', $plainToken),
        'token_expires_at' => now()->addDays(30),
    ]);

    $this->getJson('/test/device-auth', ['Authorization' => "Bearer {$plainToken}"])
        ->assertSuccessful()
        ->assertJson(['ok' => true]);
});

it('allows requests with a token that has no expiry', function () {
    $plainToken = 'no-expiry-token-'.fake()->uuid();
    Device::factory()->create([
        'access_token' => hash('sha256', $plainToken),
        'token_expires_at' => null,
    ]);

    $this->getJson('/test/device-auth', ['Authorization' => "Bearer {$plainToken}"])
        ->assertSuccessful();
});

it('sets the device on the request attributes', function () {
    $plainToken = 'attr-token-'.fake()->uuid();
    $device = Device::factory()->create([
        'access_token' => hash('sha256', $plainToken),
        'token_expires_at' => now()->addDays(30),
    ]);

    Route::get('/test/device-attr', function (Request $request) {
        return response()->json(['device_id' => $request->attributes->get('device')->id]);
    })->middleware('auth.device');

    $this->getJson('/test/device-attr', ['Authorization' => "Bearer {$plainToken}"])
        ->assertSuccessful()
        ->assertJson(['device_id' => $device->id]);
});
