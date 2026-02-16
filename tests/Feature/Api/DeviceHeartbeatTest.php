<?php

use App\Events\DeviceDisconnected;
use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\WebSocketMessageBuffer;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'heartbeat-device',
    ]);
});

function generateHeartbeatToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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
| Heartbeat endpoint
|--------------------------------------------------------------------------
*/

test('heartbeat returns status ok', function () {
    $token = generateHeartbeatToken($this->device);

    $response = $this->postJson('/api/device/heartbeat', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
        ]);
});

test('heartbeat updates last_heartbeat_at timestamp', function () {
    // Set heartbeat to an old time
    $this->device->update(['last_heartbeat_at' => now()->subMinutes(10)]);
    $oldHeartbeat = $this->device->fresh()->last_heartbeat_at;

    $token = generateHeartbeatToken($this->device);

    $this->postJson('/api/device/heartbeat', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $this->device->refresh();

    expect($this->device->last_heartbeat_at->gt($oldHeartbeat))->toBeTrue();
});

test('heartbeat with revoked device returns 401', function () {
    $this->device->update(['revoked_at' => now()]);
    $token = generateHeartbeatToken($this->device);

    $response = $this->postJson('/api/device/heartbeat', [], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'revoked_device',
        ]);
});

test('heartbeat without token returns 401', function () {
    $response = $this->postJson('/api/device/heartbeat');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'missing_token',
        ]);
});

/*
|--------------------------------------------------------------------------
| Stale device detection (device:check-heartbeats command)
|--------------------------------------------------------------------------
*/

test('stale devices are marked offline by check-heartbeats command', function () {
    Event::fake([DeviceDisconnected::class]);

    // Set heartbeat to 3 minutes ago (stale threshold is 2 minutes)
    $this->device->update(['last_heartbeat_at' => now()->subMinutes(3)]);

    $this->artisan('device:check-heartbeats')
        ->assertSuccessful();

    $this->device->refresh();

    expect($this->device->is_online)->toBeFalse();
});

test('check-heartbeats dispatches DeviceDisconnected event for stale devices', function () {
    Event::fake([DeviceDisconnected::class]);

    $this->device->update(['last_heartbeat_at' => now()->subMinutes(3)]);

    $this->artisan('device:check-heartbeats')
        ->assertSuccessful();

    Event::assertDispatched(DeviceDisconnected::class, function ($event) {
        return $event->deviceId === $this->device->id
            && $event->userId === $this->user->id;
    });
});

test('check-heartbeats calls markDisconnected on message buffer for stale devices', function () {
    Event::fake([DeviceDisconnected::class]);

    $mock = $this->mock(WebSocketMessageBuffer::class);
    $mock->shouldReceive('markDisconnected')
        ->once()
        ->with($this->device->id);

    $this->device->update(['last_heartbeat_at' => now()->subMinutes(3)]);

    $this->artisan('device:check-heartbeats')
        ->assertSuccessful();
});

test('check-heartbeats does not affect devices with recent heartbeats', function () {
    Event::fake([DeviceDisconnected::class]);

    // Heartbeat is recent (less than 2 minutes ago)
    $this->device->update(['last_heartbeat_at' => now()->subSeconds(30)]);

    $this->artisan('device:check-heartbeats')
        ->assertSuccessful();

    $this->device->refresh();

    expect($this->device->is_online)->toBeTrue();

    Event::assertNotDispatched(DeviceDisconnected::class);
});

test('check-heartbeats does not affect offline devices', function () {
    Event::fake([DeviceDisconnected::class]);

    // Device is already offline with a stale heartbeat
    $this->device->update([
        'is_online' => false,
        'last_heartbeat_at' => now()->subMinutes(10),
    ]);

    $this->artisan('device:check-heartbeats')
        ->assertSuccessful();

    Event::assertNotDispatched(DeviceDisconnected::class);
});

test('check-heartbeats handles multiple stale devices', function () {
    Event::fake([DeviceDisconnected::class]);

    $device2 = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'stale-device-2',
        'last_heartbeat_at' => now()->subMinutes(5),
    ]);

    $this->device->update(['last_heartbeat_at' => now()->subMinutes(3)]);

    $this->artisan('device:check-heartbeats')
        ->assertSuccessful();

    $this->device->refresh();
    $device2->refresh();

    expect($this->device->is_online)->toBeFalse()
        ->and($device2->is_online)->toBeFalse();

    Event::assertDispatched(DeviceDisconnected::class, 2);
});
