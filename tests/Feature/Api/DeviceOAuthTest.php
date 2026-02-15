<?php

use App\Models\CloudDeployment;
use App\Models\DeviceAuthorization;
use App\Models\OauthDeviceCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| POST /oauth/device/code — Request a device code
|--------------------------------------------------------------------------
*/

test('device code request returns valid device code', function () {
    $response = $this->postJson('/api/oauth/device/code', [
        'device_name' => 'macbook-pro',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'device_code',
            'user_code',
            'verification_uri',
            'expires_in',
            'interval',
        ]);

    $data = $response->json();
    expect($data['expires_in'])->toBe(900);
    expect($data['interval'])->toBe(5);
    expect($data['verification_uri'])->toContain('/oauth/device');

    // Verify user_code format: XXXX-XXXX
    expect($data['user_code'])->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');

    // Verify record was created in database
    $this->assertDatabaseHas('oauth_device_codes', [
        'device_code' => $data['device_code'],
        'user_code' => $data['user_code'],
        'device_name' => 'macbook-pro',
        'status' => 'pending',
    ]);
});

test('device code request requires device_name', function () {
    $response = $this->postJson('/api/oauth/device/code', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['device_name']);
});

/*
|--------------------------------------------------------------------------
| POST /oauth/device/token — Poll for token
|--------------------------------------------------------------------------
*/

test('polling with pending code returns authorization_pending', function () {
    $code = OauthDeviceCode::factory()->create();

    $response = $this->postJson('/api/oauth/device/token', [
        'device_code' => $code->device_code,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'authorization_pending',
        ]);
});

test('polling with approved code returns tokens', function () {
    $user = User::factory()->create();
    $code = OauthDeviceCode::factory()->approved()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->postJson('/api/oauth/device/token', [
        'device_code' => $code->device_code,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'refresh_token',
            'device_id',
        ]);

    $data = $response->json();
    expect($data['token_type'])->toBe('Bearer');
    expect($data['expires_in'])->toBe(3600);

    // Verify device authorization was created
    $this->assertDatabaseHas('device_authorizations', [
        'user_id' => $user->id,
        'device_name' => $code->device_name,
    ]);
});

test('polling with expired code returns expired_token', function () {
    $code = OauthDeviceCode::factory()->expired()->create();

    $response = $this->postJson('/api/oauth/device/token', [
        'device_code' => $code->device_code,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'expired_token',
        ]);
});

test('polling with denied code returns access_denied', function () {
    $code = OauthDeviceCode::factory()->denied()->create();

    $response = $this->postJson('/api/oauth/device/token', [
        'device_code' => $code->device_code,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'access_denied',
        ]);
});

test('polling with invalid code returns invalid_grant', function () {
    $response = $this->postJson('/api/oauth/device/token', [
        'device_code' => 'invalid-code-that-does-not-exist',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'invalid_grant',
        ]);
});

test('polling too fast returns slow_down', function () {
    $code = OauthDeviceCode::factory()->create([
        'last_polled_at' => now(),
    ]);

    $response = $this->postJson('/api/oauth/device/token', [
        'device_code' => $code->device_code,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'slow_down',
        ]);
});

test('polling updates last_polled_at', function () {
    $code = OauthDeviceCode::factory()->create([
        'last_polled_at' => now()->subSeconds(10),
    ]);

    $this->postJson('/api/oauth/device/token', [
        'device_code' => $code->device_code,
    ]);

    $code->refresh();
    expect($code->last_polled_at)->not->toBeNull();
    expect($code->last_polled_at->diffInSeconds(now()))->toBeLessThan(2);
});

test('polling requires device_code', function () {
    $response = $this->postJson('/api/oauth/device/token', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['device_code']);
});

/*
|--------------------------------------------------------------------------
| POST /oauth/token — Refresh token
|--------------------------------------------------------------------------
*/

test('refresh token returns new tokens', function () {
    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'refresh_token',
        ]);

    $data = $response->json();
    expect($data['token_type'])->toBe('Bearer');
    expect($data['expires_in'])->toBe(3600);

    // New refresh token should be different
    expect($data['refresh_token'])->not->toBe($refreshToken);

    // Old refresh token should no longer work
    $device->refresh();
    expect(Hash::check($refreshToken, $device->refresh_token_hash))->toBeFalse();
    expect(Hash::check($data['refresh_token'], $device->refresh_token_hash))->toBeTrue();
});

test('refresh with invalid token returns invalid_grant', function () {
    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => 'invalid-token',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'invalid_grant',
        ]);
});

test('refresh with revoked token returns invalid_grant', function () {
    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    DeviceAuthorization::factory()->revoked()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'invalid_grant',
        ]);
});

test('refresh requires valid grant_type', function () {
    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'authorization_code',
        'refresh_token' => 'some-token',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['grant_type']);
});

test('refresh requires refresh_token', function () {
    $response = $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['refresh_token']);
});

test('refresh updates last_ip on device', function () {
    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
        'last_ip' => '192.168.1.1',
    ]);

    $this->postJson('/api/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    $device->refresh();
    expect($device->last_ip)->toBe('127.0.0.1');
});

/*
|--------------------------------------------------------------------------
| POST /oauth/revoke — Revoke token
|--------------------------------------------------------------------------
*/

test('revoking a valid token marks device as revoked', function () {
    $refreshToken = Str::random(64);
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'refresh_token_hash' => Hash::make($refreshToken),
    ]);

    $response = $this->postJson('/api/oauth/revoke', [
        'token' => $refreshToken,
    ]);

    $response->assertOk();

    $device->refresh();
    expect($device->isRevoked())->toBeTrue();
    expect($device->is_online)->toBeFalse();
});

test('revoking an invalid token returns 200 per oauth spec', function () {
    $response = $this->postJson('/api/oauth/revoke', [
        'token' => 'invalid-token-that-does-not-exist',
    ]);

    $response->assertOk();
});

test('revoke requires token parameter', function () {
    $response = $this->postJson('/api/oauth/revoke', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

/*
|--------------------------------------------------------------------------
| POST /oauth/device/exchange — Exchange setup token
|--------------------------------------------------------------------------
*/

test('exchanging a valid setup token returns tokens', function () {
    $user = User::factory()->create();
    $setupToken = Str::random(64);

    $deployment = CloudDeployment::factory()->create([
        'user_id' => $user->id,
        'setup_token' => $setupToken,
        'setup_token_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/oauth/device/exchange', [
        'setup_token' => $setupToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'refresh_token',
            'device_id',
        ]);

    // Setup token should be cleared (single-use)
    $deployment->refresh();
    expect($deployment->setup_token)->toBeNull();
    expect($deployment->setup_token_expires_at)->toBeNull();

    // Device authorization should be linked to the deployment
    expect($deployment->device_authorization_id)->not->toBeNull();
});

test('exchanging an invalid setup token returns error', function () {
    $response = $this->postJson('/api/oauth/device/exchange', [
        'setup_token' => 'invalid-setup-token',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'invalid_grant',
        ]);
});

test('exchanging an expired setup token returns error', function () {
    $user = User::factory()->create();
    $setupToken = Str::random(64);

    CloudDeployment::factory()->create([
        'user_id' => $user->id,
        'setup_token' => $setupToken,
        'setup_token_expires_at' => now()->subMinutes(5),
    ]);

    $response = $this->postJson('/api/oauth/device/exchange', [
        'setup_token' => $setupToken,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'expired_token',
        ]);
});

test('setup token is single-use', function () {
    $user = User::factory()->create();
    $setupToken = Str::random(64);

    CloudDeployment::factory()->create([
        'user_id' => $user->id,
        'setup_token' => $setupToken,
        'setup_token_expires_at' => now()->addMinutes(10),
    ]);

    // First use should succeed
    $response1 = $this->postJson('/api/oauth/device/exchange', [
        'setup_token' => $setupToken,
    ]);
    $response1->assertOk();

    // Second use should fail
    $response2 = $this->postJson('/api/oauth/device/exchange', [
        'setup_token' => $setupToken,
    ]);
    $response2->assertStatus(400)
        ->assertJson(['error' => 'invalid_grant']);
});

test('exchange requires setup_token', function () {
    $response = $this->postJson('/api/oauth/device/exchange', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['setup_token']);
});

/*
|--------------------------------------------------------------------------
| Access token validation
|--------------------------------------------------------------------------
*/

test('generated access token is valid', function () {
    $user = User::factory()->create();
    $code = OauthDeviceCode::factory()->approved()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->postJson('/api/oauth/device/token', [
        'device_code' => $code->device_code,
    ]);

    $data = $response->json();
    $payload = \App\Http\Controllers\Api\DeviceOAuthController::validateAccessToken($data['access_token']);

    expect($payload)->not->toBeNull();
    expect($payload['sub'])->toBe($user->id);
    expect($payload['did'])->toBe($data['device_id']);
    expect($payload['exp'])->toBeGreaterThan(time());
});

test('tampered access token is rejected', function () {
    $payload = \App\Http\Controllers\Api\DeviceOAuthController::validateAccessToken('tampered.token');

    expect($payload)->toBeNull();
});

test('expired access token is rejected', function () {
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

    $payload = \App\Http\Controllers\Api\DeviceOAuthController::validateAccessToken($token);

    expect($payload)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Rate limiting
|--------------------------------------------------------------------------
*/

test('device code requests are rate limited', function () {
    // Make 10 requests (the limit per 15 minutes)
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/oauth/device/code', [
            'device_name' => "device-$i",
        ])->assertOk();
    }

    // 11th should be rate limited
    $this->postJson('/api/oauth/device/code', [
        'device_name' => 'device-11',
    ])->assertStatus(429);
});

/*
|--------------------------------------------------------------------------
| OAuth 2.0 error response format
|--------------------------------------------------------------------------
*/

test('all error responses include error and error_description', function () {
    // Test with invalid device code
    $response = $this->postJson('/api/oauth/device/token', [
        'device_code' => 'nonexistent',
    ]);

    $response->assertStatus(400);
    $data = $response->json();
    expect($data)->toHaveKeys(['error', 'error_description']);
});

test('rate limit responses include retry-after header', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/oauth/device/code', [
            'device_name' => "device-$i",
        ]);
    }

    $response = $this->postJson('/api/oauth/device/code', [
        'device_name' => 'device-overflow',
    ]);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
});
