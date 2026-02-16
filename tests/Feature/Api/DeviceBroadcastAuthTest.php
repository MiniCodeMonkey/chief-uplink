<?php

use App\Models\DeviceAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'test-device',
    ]);
});

function generateBroadcastAuthToken(DeviceAuthorization $device, int $expiresIn = 3600): string
{
    $payload = [
        'sub' => $device->user_id,
        'did' => $device->id,
        'iat' => time(),
        'exp' => time() + $expiresIn,
    ];

    $payloadJson = json_encode($payload);
    $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));

    return $payloadBase64.'.'.$signature;
}

/*
|--------------------------------------------------------------------------
| Successful auth
|--------------------------------------------------------------------------
*/

test('successful auth returns valid pusher signature', function () {
    $token = generateBroadcastAuthToken($this->device);
    $socketId = '123456.789012';
    $channelName = "private-chief-server.{$this->device->id}";

    $response = $this->postJson('/api/device/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => $channelName,
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['auth']);

    // Verify the auth signature format: {app_key}:{hmac_signature}
    $auth = $response->json('auth');
    $parts = explode(':', $auth);
    expect($parts)->toHaveCount(2);

    $appKey = config('reverb.apps.apps.0.key');
    expect($parts[0])->toBe($appKey);

    // Verify the HMAC signature
    $appSecret = config('reverb.apps.apps.0.secret');
    $expectedSignature = hash_hmac('sha256', "{$socketId}:{$channelName}", $appSecret);
    expect($parts[1])->toBe($expectedSignature);
});

test('auth response contains only the auth field', function () {
    $token = generateBroadcastAuthToken($this->device);

    $response = $this->postJson('/api/device/broadcasting/auth', [
        'socket_id' => '123456.789012',
        'channel_name' => "private-chief-server.{$this->device->id}",
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonStructure(['auth']);
});

/*
|--------------------------------------------------------------------------
| Mismatched device/channel (403)
|--------------------------------------------------------------------------
*/

test('returns 403 when device tries to subscribe to another device channel', function () {
    $otherDevice = DeviceAuthorization::factory()->for($this->user)->online()->create();
    $token = generateBroadcastAuthToken($this->device);

    $response = $this->postJson('/api/device/broadcasting/auth', [
        'socket_id' => '123456.789012',
        'channel_name' => "private-chief-server.{$otherDevice->id}",
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden();
    $response->assertJson([
        'error' => 'forbidden',
    ]);
});

/*
|--------------------------------------------------------------------------
| Invalid channel format (403)
|--------------------------------------------------------------------------
*/

test('returns 403 for non-chief-server channel', function () {
    $token = generateBroadcastAuthToken($this->device);

    $response = $this->postJson('/api/device/broadcasting/auth', [
        'socket_id' => '123456.789012',
        'channel_name' => "private-device.{$this->device->id}",
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden();
    $response->assertJson([
        'error' => 'forbidden',
    ]);
});

test('returns 403 for public channel name', function () {
    $token = generateBroadcastAuthToken($this->device);

    $response = $this->postJson('/api/device/broadcasting/auth', [
        'socket_id' => '123456.789012',
        'channel_name' => "chief-server.{$this->device->id}",
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden();
    $response->assertJson([
        'error' => 'forbidden',
    ]);
});

/*
|--------------------------------------------------------------------------
| Validation errors
|--------------------------------------------------------------------------
*/

test('returns 422 when socket_id is missing', function () {
    $token = generateBroadcastAuthToken($this->device);

    $response = $this->postJson('/api/device/broadcasting/auth', [
        'channel_name' => "private-chief-server.{$this->device->id}",
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertUnprocessable();
});

test('returns 422 when channel_name is missing', function () {
    $token = generateBroadcastAuthToken($this->device);

    $response = $this->postJson('/api/device/broadcasting/auth', [
        'socket_id' => '123456.789012',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertUnprocessable();
});

/*
|--------------------------------------------------------------------------
| Auth failures (handled by device.api middleware)
|--------------------------------------------------------------------------
*/

test('returns 401 for missing token', function () {
    $response = $this->postJson('/api/device/broadcasting/auth', [
        'socket_id' => '123456.789012',
        'channel_name' => "private-chief-server.{$this->device->id}",
    ]);

    $response->assertUnauthorized();
    $response->assertJson([
        'error' => 'missing_token',
    ]);
});

test('returns 401 for revoked device', function () {
    $this->device->update(['revoked_at' => now()]);
    $token = generateBroadcastAuthToken($this->device);

    $response = $this->postJson('/api/device/broadcasting/auth', [
        'socket_id' => '123456.789012',
        'channel_name' => "private-chief-server.{$this->device->id}",
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertUnauthorized();
    $response->assertJson([
        'error' => 'revoked_device',
    ]);
});
