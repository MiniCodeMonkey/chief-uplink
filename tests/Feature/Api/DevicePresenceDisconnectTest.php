<?php

use App\Events\DeviceDisconnected;
use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\WebSocketMessageBuffer;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'test-device',
    ]);
});

function generateDisconnectToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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
| Successful disconnect
|--------------------------------------------------------------------------
*/

test('successful disconnect returns status disconnected', function () {
    Event::fake([DeviceDisconnected::class]);

    $token = generateDisconnectToken($this->device);

    $response = $this->postJson('/api/device/disconnect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'disconnected',
        ]);
});

test('disconnect marks device as offline', function () {
    Event::fake([DeviceDisconnected::class]);

    $token = generateDisconnectToken($this->device);

    $this->postJson('/api/device/disconnect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $this->device->refresh();

    expect($this->device->is_online)->toBeFalse();
});

test('disconnect dispatches DeviceDisconnected event', function () {
    Event::fake([DeviceDisconnected::class]);

    $token = generateDisconnectToken($this->device);

    $this->postJson('/api/device/disconnect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    Event::assertDispatched(DeviceDisconnected::class, function ($event) {
        return $event->deviceId === $this->device->id
            && $event->userId === $this->user->id;
    });
});

test('disconnect calls markDisconnected on message buffer', function () {
    Event::fake([DeviceDisconnected::class]);

    $mock = $this->mock(WebSocketMessageBuffer::class);
    $mock->shouldReceive('markDisconnected')
        ->once()
        ->with($this->device->id);

    $token = generateDisconnectToken($this->device);

    $this->postJson('/api/device/disconnect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);
});

test('device is offline after disconnect', function () {
    Event::fake([DeviceDisconnected::class]);

    $token = generateDisconnectToken($this->device);

    // Verify device is online before disconnect
    expect($this->device->is_online)->toBeTrue();

    $this->postJson('/api/device/disconnect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $this->device->refresh();

    expect($this->device->is_online)->toBeFalse()
        ->and($this->device->session_id)->not->toBeNull(); // session_id preserved for buffer
});

/*
|--------------------------------------------------------------------------
| Authentication failures
|--------------------------------------------------------------------------
*/

test('disconnect with revoked device returns 401', function () {
    $this->device->update(['revoked_at' => now()]);
    $token = generateDisconnectToken($this->device);

    $response = $this->postJson('/api/device/disconnect', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'revoked_device',
        ]);
});

test('disconnect without token returns 401', function () {
    $response = $this->postJson('/api/device/disconnect');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'missing_token',
        ]);
});
