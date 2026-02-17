<?php

use App\Events\DeviceConnected;
use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\WebSocketMessageBuffer;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->create([
        'device_name' => 'test-device',
        'is_online' => false,
    ]);
});

function generateDeviceToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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
| Successful connect
|--------------------------------------------------------------------------
*/

test('successful connect returns welcome response with reverb config', function () {
    Event::fake([DeviceConnected::class]);

    $token = generateDeviceToken($this->device);

    $response = $this->postJson('/api/device/connect', [
        'chief_version' => '0.6.0',
        'device_name' => 'my-macbook',
        'os' => 'darwin',
        'arch' => 'arm64',
        'protocol_version' => 1,
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'type',
            'protocol_version',
            'device_id',
            'session_id',
            'reverb' => ['key', 'host', 'port', 'scheme'],
        ])
        ->assertJson([
            'type' => 'welcome',
            'protocol_version' => 1,
            'device_id' => $this->device->id,
        ]);

    // Verify session_id is a valid UUID
    $sessionId = $response->json('session_id');
    expect($sessionId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

test('connect returns reverb port as integer', function () {
    Event::fake([DeviceConnected::class]);

    $token = generateDeviceToken($this->device);

    $response = $this->postJson('/api/device/connect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk();

    $port = $response->json('reverb.port');
    expect($port)->toBeInt();
});

test('connect updates device record with metadata', function () {
    Event::fake([DeviceConnected::class]);

    $token = generateDeviceToken($this->device);

    $this->postJson('/api/device/connect', [
        'chief_version' => '0.6.0',
        'device_name' => 'my-macbook',
        'os' => 'darwin',
        'arch' => 'arm64',
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $this->device->refresh();

    expect($this->device->is_online)->toBeTrue()
        ->and($this->device->chief_version)->toBe('0.6.0')
        ->and($this->device->device_name)->toBe('my-macbook')
        ->and($this->device->os)->toBe('darwin')
        ->and($this->device->arch)->toBe('arm64')
        ->and($this->device->session_id)->not->toBeNull()
        ->and($this->device->last_connected_at)->not->toBeNull();
});

test('connect dispatches DeviceConnected event', function () {
    Event::fake([DeviceConnected::class]);

    $token = generateDeviceToken($this->device);

    $this->postJson('/api/device/connect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    Event::assertDispatched(DeviceConnected::class, function ($event) {
        return $event->deviceId === $this->device->id
            && $event->userId === $this->user->id;
    });
});

test('connect stores session_id on device record', function () {
    Event::fake([DeviceConnected::class]);

    $token = generateDeviceToken($this->device);

    $response = $this->postJson('/api/device/connect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $sessionId = $response->json('session_id');
    $this->device->refresh();

    expect($this->device->session_id)->toBe($sessionId);
});

test('connect calls markReconnected on message buffer', function () {
    Event::fake([DeviceConnected::class]);

    $mock = $this->mock(WebSocketMessageBuffer::class);
    $mock->shouldReceive('markReconnected')
        ->once()
        ->with($this->device->id);

    $token = generateDeviceToken($this->device);

    $this->postJson('/api/device/connect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);
});

/*
|--------------------------------------------------------------------------
| Duplicate connect (reconnection)
|--------------------------------------------------------------------------
*/

test('duplicate connect replaces existing session', function () {
    Event::fake([DeviceConnected::class]);

    $token = generateDeviceToken($this->device);

    // First connect
    $response1 = $this->postJson('/api/device/connect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);
    $session1 = $response1->json('session_id');

    // Second connect (reconnection)
    $response2 = $this->postJson('/api/device/connect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);
    $session2 = $response2->json('session_id');

    // Sessions should be different
    expect($session1)->not->toBe($session2);

    // Device should have the new session
    $this->device->refresh();
    expect($this->device->session_id)->toBe($session2)
        ->and($this->device->is_online)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Connect without optional metadata
|--------------------------------------------------------------------------
*/

test('connect works without optional metadata fields', function () {
    Event::fake([DeviceConnected::class]);

    $token = generateDeviceToken($this->device);

    $response = $this->postJson('/api/device/connect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJson([
            'type' => 'welcome',
            'protocol_version' => 1,
            'device_id' => $this->device->id,
        ]);

    // Original device_name should be preserved since we didn't send a new one
    $this->device->refresh();
    expect($this->device->device_name)->toBe('test-device')
        ->and($this->device->is_online)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Authentication failures
|--------------------------------------------------------------------------
*/

test('connect with revoked device returns 401', function () {
    $this->device->update(['revoked_at' => now()]);
    $token = generateDeviceToken($this->device);

    $response = $this->postJson('/api/device/connect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'revoked_device',
        ]);
});

test('connect without token returns 401', function () {
    $response = $this->postJson('/api/device/connect');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'missing_token',
        ]);
});
