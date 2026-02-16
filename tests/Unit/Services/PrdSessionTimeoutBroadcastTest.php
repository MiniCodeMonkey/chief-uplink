<?php

use App\Events\ChiefCommandDispatched;
use App\Events\ChiefMessageReceived;
use App\Services\PrdSessionManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

test('sendTimeoutWarning dispatches ChiefCommandDispatched to CLI channel', function () {
    Event::fake([ChiefMessageReceived::class, ChiefCommandDispatched::class]);

    $manager = new PrdSessionManager;
    $session = [
        'device_id' => 42,
        'user_id' => 7,
        'prd_id' => null,
        'created_at' => time() - 1500,
        'last_activity_at' => time() - 1500,
    ];

    // Call protected method via reflection
    $method = new ReflectionMethod(PrdSessionManager::class, 'sendTimeoutWarning');
    $method->invoke($manager, 'session-123', $session, 5);

    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
        return $event->deviceId === 42
            && $event->userId === 7
            && $event->command['type'] === 'session_timeout_warning'
            && $event->command['session_id'] === 'session-123'
            && $event->command['minutes_remaining'] === 5;
    });
});

test('sendTimeoutWarning dispatches ChiefMessageReceived to browser channel', function () {
    Event::fake([ChiefMessageReceived::class, ChiefCommandDispatched::class]);

    $manager = new PrdSessionManager;
    $session = [
        'device_id' => 42,
        'user_id' => 7,
        'prd_id' => null,
        'created_at' => time() - 1500,
        'last_activity_at' => time() - 1500,
    ];

    $method = new ReflectionMethod(PrdSessionManager::class, 'sendTimeoutWarning');
    $method->invoke($manager, 'session-123', $session, 5);

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->deviceId === 42
            && $event->userId === 7
            && $event->message['type'] === 'session_timeout_warning'
            && $event->message['session_id'] === 'session-123'
            && $event->message['minutes_remaining'] === 5;
    });
});

test('expireSession dispatches ChiefCommandDispatched to CLI channel', function () {
    Event::fake([ChiefMessageReceived::class, ChiefCommandDispatched::class]);
    Redis::shouldReceive('del')->once();
    Redis::shouldReceive('zrem')->once();
    Redis::shouldReceive('srem')->once();
    Redis::shouldReceive('hgetall')->andReturn([
        'device_id' => '42',
        'user_id' => '7',
        'prd_id' => '',
        'created_at' => (string) (time() - 1800),
        'last_activity_at' => (string) (time() - 1800),
    ]);

    $manager = new PrdSessionManager;
    $session = [
        'device_id' => 42,
        'user_id' => 7,
        'prd_id' => null,
        'created_at' => time() - 1800,
        'last_activity_at' => time() - 1800,
    ];

    $method = new ReflectionMethod(PrdSessionManager::class, 'expireSession');
    $method->invoke($manager, 'expired-session-456', $session);

    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) {
        return $event->deviceId === 42
            && $event->userId === 7
            && $event->command['type'] === 'session_expired'
            && $event->command['session_id'] === 'expired-session-456';
    });
});

test('expireSession dispatches ChiefMessageReceived to browser channel', function () {
    Event::fake([ChiefMessageReceived::class, ChiefCommandDispatched::class]);
    Redis::shouldReceive('del')->once();
    Redis::shouldReceive('zrem')->once();
    Redis::shouldReceive('srem')->once();
    Redis::shouldReceive('hgetall')->andReturn([
        'device_id' => '42',
        'user_id' => '7',
        'prd_id' => '',
        'created_at' => (string) (time() - 1800),
        'last_activity_at' => (string) (time() - 1800),
    ]);

    $manager = new PrdSessionManager;
    $session = [
        'device_id' => 42,
        'user_id' => 7,
        'prd_id' => null,
        'created_at' => time() - 1800,
        'last_activity_at' => time() - 1800,
    ];

    $method = new ReflectionMethod(PrdSessionManager::class, 'expireSession');
    $method->invoke($manager, 'expired-session-456', $session);

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) {
        return $event->deviceId === 42
            && $event->userId === 7
            && $event->message['type'] === 'session_expired'
            && $event->message['session_id'] === 'expired-session-456';
    });
});

test('timeout warning broadcasts same message payload to both CLI and browser', function () {
    Event::fake([ChiefMessageReceived::class, ChiefCommandDispatched::class]);

    $manager = new PrdSessionManager;
    $session = [
        'device_id' => 10,
        'user_id' => 3,
        'prd_id' => 'main',
        'created_at' => time() - 1200,
        'last_activity_at' => time() - 1200,
    ];

    $method = new ReflectionMethod(PrdSessionManager::class, 'sendTimeoutWarning');
    $method->invoke($manager, 'dual-session', $session, 1);

    // Both events should receive the same message structure
    $expectedMessage = [
        'type' => 'session_timeout_warning',
        'session_id' => 'dual-session',
        'minutes_remaining' => 1,
    ];

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) use ($expectedMessage) {
        return $event->message === $expectedMessage;
    });

    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) use ($expectedMessage) {
        return $event->command === $expectedMessage;
    });
});

test('session expiry broadcasts same message payload to both CLI and browser', function () {
    Event::fake([ChiefMessageReceived::class, ChiefCommandDispatched::class]);
    Redis::shouldReceive('del')->once();
    Redis::shouldReceive('zrem')->once();
    Redis::shouldReceive('srem')->once();
    Redis::shouldReceive('hgetall')->andReturn([
        'device_id' => '10',
        'user_id' => '3',
        'prd_id' => 'main',
        'created_at' => (string) (time() - 1800),
        'last_activity_at' => (string) (time() - 1800),
    ]);

    $manager = new PrdSessionManager;
    $session = [
        'device_id' => 10,
        'user_id' => 3,
        'prd_id' => 'main',
        'created_at' => time() - 1800,
        'last_activity_at' => time() - 1800,
    ];

    $method = new ReflectionMethod(PrdSessionManager::class, 'expireSession');
    $method->invoke($manager, 'expired-dual', $session);

    $expectedMessage = [
        'type' => 'session_expired',
        'session_id' => 'expired-dual',
    ];

    Event::assertDispatched(ChiefMessageReceived::class, function ($event) use ($expectedMessage) {
        return $event->message === $expectedMessage;
    });

    Event::assertDispatched(ChiefCommandDispatched::class, function ($event) use ($expectedMessage) {
        return $event->command === $expectedMessage;
    });
});
