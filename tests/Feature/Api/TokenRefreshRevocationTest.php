<?php

use App\Events\DeviceTokenRevoked;
use App\Http\Controllers\Api\DeviceOAuthController;
use App\Models\DeviceAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Access Token Validation Middleware
|--------------------------------------------------------------------------
*/

test('valid access token passes middleware', function () {
    $user = User::factory()->create();
    $refreshToken = Str::random(64);
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    // Generate a valid access token
    $tokenResponse = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    $accessToken = $tokenResponse->json('access_token');

    $response = $this->getJson('/api/device/status', [
        'Authorization' => "Bearer {$accessToken}",
    ]);

    $response->assertOk()
        ->assertJson([
            'authenticated' => true,
            'device_id' => $device->id,
            'user_id' => $user->id,
        ]);
});

test('missing access token returns 401', function () {
    $response = $this->getJson('/api/device/status');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'invalid_token',
        ]);
});

test('invalid access token returns 401', function () {
    $response = $this->getJson('/api/device/status', [
        'Authorization' => 'Bearer invalid.token',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'invalid_token',
        ]);
});

test('expired access token returns 401', function () {
    // Create a token that's already expired
    $payloadData = [
        'sub' => 1,
        'did' => 1,
        'iat' => time() - 7200,
        'exp' => time() - 3600,
    ];
    $payloadJson = json_encode($payloadData);
    $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));
    $token = $payloadBase64.'.'.$signature;

    $response = $this->getJson('/api/device/status', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(401);
});

test('access token for revoked device returns 401', function () {
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->revoked()->create([
        'user_id' => $user->id,
    ]);

    // Generate a valid access token payload for the revoked device
    $payloadData = [
        'sub' => $user->id,
        'did' => $device->id,
        'iat' => time(),
        'exp' => time() + 3600,
    ];
    $payloadJson = json_encode($payloadData);
    $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));
    $token = $payloadBase64.'.'.$signature;

    $response = $this->getJson('/api/device/status', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'invalid_token',
            'error_description' => 'The device has been revoked.',
        ]);
});

test('access token for deleted device returns 401', function () {
    $payloadData = [
        'sub' => 999,
        'did' => 999,
        'iat' => time(),
        'exp' => time() + 3600,
    ];
    $payloadJson = json_encode($payloadData);
    $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));
    $token = $payloadBase64.'.'.$signature;

    $response = $this->getJson('/api/device/status', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Refresh Token Rotation
|--------------------------------------------------------------------------
*/

test('refresh token rotation stores previous hash for compromise detection', function () {
    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    $originalHash = Hash::make($refreshToken);
    $device = DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => $originalHash,
    ]);

    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    $response->assertOk();

    $device->refresh();
    // Previous hash should be stored
    expect($device->previous_refresh_token_hash)->toBe($originalHash);
    // Current hash should be different
    expect($device->refresh_token_hash)->not->toBe($originalHash);
    // New refresh token should work with current hash
    expect(Hash::check($response->json('refresh_token'), $device->refresh_token_hash))->toBeTrue();
});

test('old refresh token no longer works after rotation', function () {
    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    // First refresh should succeed
    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);
    $response->assertOk();

    // Using the old token again should fail
    $response2 = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);
    $response2->assertStatus(400)
        ->assertJson(['error' => 'invalid_grant']);
});

test('new refresh token works after rotation', function () {
    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    // First refresh
    $response1 = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);
    $newToken = $response1->json('refresh_token');

    // Second refresh with new token
    $response2 = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $newToken,
    ]);
    $response2->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'refresh_token',
        ]);
});

/*
|--------------------------------------------------------------------------
| Compromise Detection — Revoked Token Reuse
|--------------------------------------------------------------------------
*/

test('reusing a previously-rotated refresh token revokes the device', function () {
    Event::fake();

    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    // Rotate the token (legitimate use)
    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);
    $response->assertOk();

    // Reuse the old token (compromise detection)
    $response2 = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);
    $response2->assertStatus(400)
        ->assertJson(['error' => 'invalid_grant']);

    // Device should be revoked
    $device->refresh();
    expect($device->isRevoked())->toBeTrue();
    expect($device->is_online)->toBeFalse();

    // Event should be dispatched
    Event::assertDispatched(DeviceTokenRevoked::class, function ($event) use ($device) {
        return $event->deviceId === $device->id && $event->userId === $device->user_id;
    });
});

test('compromise detection does not affect other devices', function () {
    $refreshToken1 = Str::random(64);
    $refreshToken2 = Str::random(64);
    $user = User::factory()->create();

    $device1 = DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken1),
    ]);
    $device2 = DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken2),
    ]);

    // Rotate device1's token
    $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken1,
    ])->assertOk();

    // Reuse device1's old token (triggers compromise detection)
    $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken1,
    ]);

    // Device1 should be revoked
    $device1->refresh();
    expect($device1->isRevoked())->toBeTrue();

    // Device2 should NOT be affected
    $device2->refresh();
    expect($device2->isRevoked())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Revocation & WebSocket Disconnection
|--------------------------------------------------------------------------
*/

test('revoking a token dispatches DeviceTokenRevoked event', function () {
    Event::fake();

    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    $this->postJson('/api/oauth/revoke', [
        'token' => $refreshToken,
    ])->assertOk();

    Event::assertDispatched(DeviceTokenRevoked::class, function ($event) use ($device) {
        return $event->deviceId === $device->id && $event->userId === $device->user_id;
    });
});

test('revocation immediately marks device offline', function () {
    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    expect($device->is_online)->toBeTrue();

    $this->postJson('/api/oauth/revoke', [
        'token' => $refreshToken,
    ])->assertOk();

    $device->refresh();
    expect($device->is_online)->toBeFalse();
    expect($device->isRevoked())->toBeTrue();
});

test('revoked device cannot use access token for authenticated requests', function () {
    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    // Get a valid access token
    $tokenResponse = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);
    $accessToken = $tokenResponse->json('access_token');
    $newRefreshToken = $tokenResponse->json('refresh_token');

    // Verify it works before revocation
    $this->getJson('/api/device/status', [
        'Authorization' => "Bearer {$accessToken}",
    ])->assertOk();

    // Revoke the device
    $this->postJson('/api/oauth/revoke', [
        'token' => $newRefreshToken,
    ])->assertOk();

    // Access token should now be rejected (device is revoked)
    $this->getJson('/api/device/status', [
        'Authorization' => "Bearer {$accessToken}",
    ])->assertStatus(401);
});

test('revoking invalid token does not dispatch event', function () {
    Event::fake();

    $this->postJson('/api/oauth/revoke', [
        'token' => 'nonexistent-token',
    ])->assertOk();

    Event::assertNotDispatched(DeviceTokenRevoked::class);
});

/*
|--------------------------------------------------------------------------
| Token Validation Performance
|--------------------------------------------------------------------------
*/

test('access token validation is performant', function () {
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
    ]);

    // Generate a valid token
    $payloadData = [
        'sub' => $user->id,
        'did' => $device->id,
        'iat' => time(),
        'exp' => time() + 3600,
    ];
    $payloadJson = json_encode($payloadData);
    $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));
    $token = $payloadBase64.'.'.$signature;

    // Validate 100 times and ensure it's fast
    $start = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        DeviceOAuthController::validateAccessToken($token);
    }
    $elapsed = (microtime(true) - $start) * 1000; // milliseconds

    // 100 validations should complete well under 100ms (sub-1ms each)
    expect($elapsed)->toBeLessThan(100);
});

/*
|--------------------------------------------------------------------------
| DeviceTokenRevoked Event
|--------------------------------------------------------------------------
*/

test('DeviceTokenRevoked event broadcasts on correct channels', function () {
    $event = new DeviceTokenRevoked(deviceId: 42, userId: 7);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);
    expect($channels[0]->name)->toBe('private-user.7');
    expect($channels[1]->name)->toBe('private-device.42');
});

test('DeviceTokenRevoked event has correct broadcast name', function () {
    $event = new DeviceTokenRevoked(deviceId: 1, userId: 1);

    expect($event->broadcastAs())->toBe('device.token.revoked');
});

/*
|--------------------------------------------------------------------------
| Multiple Token Rotation Chain
|--------------------------------------------------------------------------
*/

test('token can be rotated multiple times in sequence', function () {
    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    $currentToken = $refreshToken;

    // Rotate 5 times
    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $currentToken,
        ]);

        $response->assertOk();
        $currentToken = $response->json('refresh_token');
        expect($currentToken)->not->toBeNull();
    }
});

/*
|--------------------------------------------------------------------------
| WebSocket URL in Token Responses
|--------------------------------------------------------------------------
*/

test('token response includes ws_url from reverb config', function () {
    config()->set('reverb.apps.apps.0.options.host', 'ws-abc123-reverb.laravel.cloud');
    config()->set('reverb.apps.apps.0.options.port', 443);
    config()->set('reverb.apps.apps.0.options.scheme', 'https');

    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('ws_url', 'wss://ws-abc123-reverb.laravel.cloud/ws/server');
});

test('token response includes ws_url on device approval', function () {
    config()->set('reverb.apps.apps.0.options.host', 'ws-abc123-reverb.laravel.cloud');
    config()->set('reverb.apps.apps.0.options.port', 443);
    config()->set('reverb.apps.apps.0.options.scheme', 'https');

    $user = User::factory()->create();
    $deviceCode = \App\Models\OauthDeviceCode::create([
        'device_code' => 'test-device-code',
        'user_code' => 'ABCD-1234',
        'device_name' => 'test-device',
        'user_id' => $user->id,
        'status' => 'approved',
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->postJson('/api/oauth/device/token', [
        'device_code' => 'test-device-code',
    ]);

    $response->assertOk()
        ->assertJsonPath('ws_url', 'wss://ws-abc123-reverb.laravel.cloud/ws/server');
});

test('ws_url omits default port for wss', function () {
    config()->set('reverb.apps.apps.0.options.host', 'ws-host.example.com');
    config()->set('reverb.apps.apps.0.options.port', 443);
    config()->set('reverb.apps.apps.0.options.scheme', 'https');

    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('ws_url', 'wss://ws-host.example.com/ws/server');
});

test('ws_url includes non-default port', function () {
    config()->set('reverb.apps.apps.0.options.host', 'ws-host.example.com');
    config()->set('reverb.apps.apps.0.options.port', 8080);
    config()->set('reverb.apps.apps.0.options.scheme', 'https');

    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('ws_url', 'wss://ws-host.example.com:8080/ws/server');
});

test('ws_url uses ws scheme for http', function () {
    config()->set('reverb.apps.apps.0.options.host', 'localhost');
    config()->set('reverb.apps.apps.0.options.port', 80);
    config()->set('reverb.apps.apps.0.options.scheme', 'http');

    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('ws_url', 'ws://localhost/ws/server');
});

test('ws_url is null when reverb host not configured', function () {
    config()->set('reverb.apps.apps.0.options.host', null);

    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('ws_url', null);
});

test('only the most recent previous token triggers compromise detection', function () {
    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    $firstToken = $refreshToken;

    // Rotate token twice
    $response1 = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $firstToken,
    ]);
    $secondToken = $response1->json('refresh_token');

    $response2 = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $secondToken,
    ]);
    $response2->assertOk();

    // Reusing the second token (the previous one) should trigger compromise detection
    $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $secondToken,
    ])->assertStatus(400);

    $device->refresh();
    expect($device->isRevoked())->toBeTrue();
});
