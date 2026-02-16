<?php

use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\WebSocketMessageBuffer;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    // Flush rate limiter's internal cache to avoid cross-test pollution
    $rateLimiter = app(RateLimiter::class);
    $cache = (new ReflectionProperty($rateLimiter, 'cache'))->getValue($rateLimiter);
    $cache->flush();

    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'rate-limit-device',
    ]);
    $this->mock(WebSocketMessageBuffer::class);
    Event::fake();
});

function generateRateLimitToken(DeviceAuthorization $device, int $expiresIn = 3600): string
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
| Rate limiter configuration
|--------------------------------------------------------------------------
*/

test('device-connect rate limiter is configured', function () {
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('device-connect');
    expect($limiter)->not->toBeNull();
});

test('device-disconnect rate limiter is configured', function () {
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('device-disconnect');
    expect($limiter)->not->toBeNull();
});

test('device-messages rate limiter is configured', function () {
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('device-messages');
    expect($limiter)->not->toBeNull();
});

test('device-heartbeat rate limiter is configured', function () {
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('device-heartbeat');
    expect($limiter)->not->toBeNull();
});

test('device-broadcasting-auth rate limiter is configured', function () {
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('device-broadcasting-auth');
    expect($limiter)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Rate limiters keyed by device_id
|--------------------------------------------------------------------------
*/

test('rate limiters are keyed by device_id', function () {
    $request = \Illuminate\Http\Request::create('/test', 'POST');
    $request->attributes->set('device_id', 42);

    $limiters = ['device-connect', 'device-disconnect', 'device-messages', 'device-heartbeat', 'device-broadcasting-auth'];

    foreach ($limiters as $limiterName) {
        $limiter = \Illuminate\Support\Facades\RateLimiter::limiter($limiterName);
        $limit = $limiter($request);
        expect($limit)->toBeInstanceOf(\Illuminate\Cache\RateLimiting\Limit::class);
    }
});

/*
|--------------------------------------------------------------------------
| Connect endpoint rate limiting — 10 per device per minute
|--------------------------------------------------------------------------
*/

test('connect endpoint returns 429 after exceeding rate limit', function () {
    $token = generateRateLimitToken($this->device);

    // Make 10 successful requests (the limit)
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/device/connect', [
            'chief_version' => '1.0.0',
            'device_name' => 'test-device',
            'os' => 'linux',
            'arch' => 'amd64',
            'protocol_version' => 2,
        ], ['Authorization' => "Bearer $token"])->assertStatus(200);
    }

    // 11th request should be rate limited
    $response = $this->postJson('/api/device/connect', [
        'chief_version' => '1.0.0',
        'device_name' => 'test-device',
        'os' => 'linux',
        'arch' => 'amd64',
        'protocol_version' => 2,
    ], ['Authorization' => "Bearer $token"]);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
});

/*
|--------------------------------------------------------------------------
| Messages endpoint rate limiting — 60 per device per minute
|--------------------------------------------------------------------------
*/

test('messages endpoint returns 429 after exceeding rate limit', function () {
    $token = generateRateLimitToken($this->device);

    // Make 60 successful requests (the limit)
    for ($i = 0; $i < 60; $i++) {
        $this->postJson('/api/device/messages', [
            'batch_id' => \Illuminate\Support\Str::uuid()->toString(),
            'messages' => [
                ['type' => 'log_lines', 'id' => "msg-$i", 'timestamp' => now()->toISOString(), 'lines' => ['test']],
            ],
        ], ['Authorization' => "Bearer $token"])->assertStatus(200);
    }

    // 61st request should be rate limited
    $response = $this->postJson('/api/device/messages', [
        'batch_id' => \Illuminate\Support\Str::uuid()->toString(),
        'messages' => [
            ['type' => 'log_lines', 'id' => 'msg-extra', 'timestamp' => now()->toISOString(), 'lines' => ['test']],
        ],
    ], ['Authorization' => "Bearer $token"]);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
});

/*
|--------------------------------------------------------------------------
| Heartbeat endpoint rate limiting — 5 per device per minute
|--------------------------------------------------------------------------
*/

test('heartbeat endpoint returns 429 after exceeding rate limit', function () {
    $token = generateRateLimitToken($this->device);

    // Make 5 successful requests (the limit)
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/device/heartbeat', [], ['Authorization' => "Bearer $token"])
            ->assertStatus(200);
    }

    // 6th request should be rate limited
    $response = $this->postJson('/api/device/heartbeat', [], ['Authorization' => "Bearer $token"]);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
});

/*
|--------------------------------------------------------------------------
| Rate limits are per-device, not per-IP
|--------------------------------------------------------------------------
*/

test('rate limits are independent per device', function () {
    // Create two fresh devices to avoid interference from other tests' rate limit state
    $deviceA = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'independence-device-a',
    ]);
    $deviceB = DeviceAuthorization::factory()->for($this->user)->online()->create([
        'device_name' => 'independence-device-b',
    ]);

    expect($deviceA->id)->not->toBe($deviceB->id);

    $tokenA = generateRateLimitToken($deviceA);
    $tokenB = generateRateLimitToken($deviceB);

    // Exhaust connect limit for device A (10 requests) — using connect since heartbeat limit (5) is low
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/device/connect', [
            'chief_version' => '1.0.0',
            'device_name' => 'test-device',
            'os' => 'linux',
            'arch' => 'amd64',
            'protocol_version' => 2,
        ], ['Authorization' => "Bearer $tokenA"])->assertStatus(200);
    }

    // Device A is now rate limited
    $this->postJson('/api/device/connect', [
        'chief_version' => '1.0.0',
        'device_name' => 'test-device',
        'os' => 'linux',
        'arch' => 'amd64',
        'protocol_version' => 2,
    ], ['Authorization' => "Bearer $tokenA"])->assertStatus(429);

    // Device B should still be able to make requests
    $this->postJson('/api/device/connect', [
        'chief_version' => '1.0.0',
        'device_name' => 'test-device',
        'os' => 'linux',
        'arch' => 'amd64',
        'protocol_version' => 2,
    ], ['Authorization' => "Bearer $tokenB"])->assertStatus(200);
});

/*
|--------------------------------------------------------------------------
| Disconnect endpoint rate limiting — 10 per device per minute
|--------------------------------------------------------------------------
*/

test('disconnect endpoint returns 429 after exceeding rate limit', function () {
    $token = generateRateLimitToken($this->device);

    // Make 10 successful requests (the limit)
    for ($i = 0; $i < 10; $i++) {
        // Re-set device to online before each disconnect
        $this->device->update(['is_online' => true]);

        $this->postJson('/api/device/disconnect', [], ['Authorization' => "Bearer $token"])
            ->assertStatus(200);
    }

    // 11th request should be rate limited
    $this->device->update(['is_online' => true]);
    $response = $this->postJson('/api/device/disconnect', [], ['Authorization' => "Bearer $token"]);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
});

/*
|--------------------------------------------------------------------------
| Broadcasting auth endpoint rate limiting — 10 per device per minute
|--------------------------------------------------------------------------
*/

test('broadcasting auth endpoint returns 429 after exceeding rate limit', function () {
    $token = generateRateLimitToken($this->device);
    $deviceId = $this->device->id;

    // Make 10 successful requests (the limit)
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/device/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => "private-chief-server.{$deviceId}",
        ], ['Authorization' => "Bearer $token"])->assertStatus(200);
    }

    // 11th request should be rate limited
    $response = $this->postJson('/api/device/broadcasting/auth', [
        'socket_id' => '12345.67890',
        'channel_name' => "private-chief-server.{$deviceId}",
    ], ['Authorization' => "Bearer $token"]);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
});
