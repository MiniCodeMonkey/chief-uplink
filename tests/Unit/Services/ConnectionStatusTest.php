<?php

use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\ServerConnectionManager;

beforeEach(function () {
    $this->manager = new ServerConnectionManager;
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create();
});

test('new connection manager has no connections', function () {
    expect($this->manager->getActiveConnections())->toBeEmpty();
});

test('isAuthenticated returns false for unknown connection', function () {
    expect($this->manager->isAuthenticated(999))->toBeFalse();
});

test('getDeviceId returns null for unknown connection', function () {
    expect($this->manager->getDeviceId(999))->toBeNull();
});

test('getUserId returns null for unknown connection', function () {
    expect($this->manager->getUserId(999))->toBeNull();
});

test('getConnectionIdForDevice returns null for unconnected device', function () {
    expect($this->manager->getConnectionIdForDevice(999))->toBeNull();
});

test('handleHello fails without hello type', function () {
    $result = $this->manager->handleHello(1, ['type' => 'not-hello']);

    expect($result['success'])->toBeFalse();
    expect($result['response']['type'])->toBe('auth_failed');
});

test('handleHello fails without access token', function () {
    $result = $this->manager->handleHello(1, ['type' => 'hello']);

    expect($result['success'])->toBeFalse();
    expect($result['response']['code'])->toBe('AUTH_FAILED');
});

test('handleHello fails with invalid access token', function () {
    $result = $this->manager->handleHello(1, [
        'type' => 'hello',
        'access_token' => 'invalid.token',
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['response']['type'])->toBe('auth_failed');
});

test('handleHello fails for revoked device', function () {
    $device = DeviceAuthorization::factory()->for($this->user)->revoked()->create();

    // Generate a valid token for this device
    $controller = new \App\Http\Controllers\Api\DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');
    $token = $method->invoke($controller, $device);

    $result = $this->manager->handleHello(1, [
        'type' => 'hello',
        'access_token' => $token,
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['response']['message'])->toContain('revoked');
});

test('handleHello succeeds with valid token', function () {
    $controller = new \App\Http\Controllers\Api\DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');
    $token = $method->invoke($controller, $this->device);

    \Illuminate\Support\Facades\Event::fake();

    $result = $this->manager->handleHello(1, [
        'type' => 'hello',
        'access_token' => $token,
        'chief_version' => '0.5.0',
        'os' => 'linux',
        'arch' => 'amd64',
    ]);

    expect($result['success'])->toBeTrue();
    expect($result['response']['type'])->toBe('welcome');
    expect($result['response']['device_id'])->toBe($this->device->id);
});

test('after hello, connection is authenticated', function () {
    $controller = new \App\Http\Controllers\Api\DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');
    $token = $method->invoke($controller, $this->device);

    \Illuminate\Support\Facades\Event::fake();

    $this->manager->handleHello(1, [
        'type' => 'hello',
        'access_token' => $token,
    ]);

    expect($this->manager->isAuthenticated(1))->toBeTrue();
    expect($this->manager->getDeviceId(1))->toBe($this->device->id);
    expect($this->manager->getUserId(1))->toBe($this->user->id);
});

test('handleDisconnect cleans up connection', function () {
    $controller = new \App\Http\Controllers\Api\DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');
    $token = $method->invoke($controller, $this->device);

    \Illuminate\Support\Facades\Event::fake();

    $this->manager->handleHello(1, [
        'type' => 'hello',
        'access_token' => $token,
    ]);

    $this->manager->handleDisconnect(1);

    expect($this->manager->isAuthenticated(1))->toBeFalse();
    expect($this->manager->getDeviceId(1))->toBeNull();
    expect($this->manager->getConnectionIdForDevice($this->device->id))->toBeNull();
});

test('handleDisconnect marks device offline', function () {
    $controller = new \App\Http\Controllers\Api\DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');
    $token = $method->invoke($controller, $this->device);

    \Illuminate\Support\Facades\Event::fake();

    $this->manager->handleHello(1, [
        'type' => 'hello',
        'access_token' => $token,
    ]);

    $this->manager->handleDisconnect(1);

    $this->device->refresh();
    expect($this->device->is_online)->toBeFalse();
});

test('getConnectionsNeedingRefresh returns connections with expiring tokens', function () {
    $controller = new \App\Http\Controllers\Api\DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');
    $token = $method->invoke($controller, $this->device);

    \Illuminate\Support\Facades\Event::fake();

    $this->manager->handleHello(1, [
        'type' => 'hello',
        'access_token' => $token,
    ]);

    // Token expires in 1 hour, threshold is 5 min by default
    $needsRefresh = $this->manager->getConnectionsNeedingRefresh();
    expect($needsRefresh)->toBeEmpty();

    // With a very large threshold, should include all connections
    $needsRefresh = $this->manager->getConnectionsNeedingRefresh(4000);
    expect($needsRefresh)->toHaveCount(1);
});

test('disconnectDevice removes connection by device id', function () {
    $controller = new \App\Http\Controllers\Api\DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');
    $token = $method->invoke($controller, $this->device);

    \Illuminate\Support\Facades\Event::fake();

    $this->manager->handleHello(1, [
        'type' => 'hello',
        'access_token' => $token,
    ]);

    $connectionId = $this->manager->disconnectDevice($this->device->id);

    expect($connectionId)->toBe(1);
    expect($this->manager->isAuthenticated(1))->toBeFalse();
});

test('isDeviceOnline returns false without connection object', function () {
    $controller = new \App\Http\Controllers\Api\DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');
    $token = $method->invoke($controller, $this->device);

    \Illuminate\Support\Facades\Event::fake();

    $this->manager->handleHello(1, [
        'type' => 'hello',
        'access_token' => $token,
    ]);

    // No connection object registered
    expect($this->manager->isDeviceOnline($this->device->id))->toBeFalse();
});

test('getSessionId returns session after hello', function () {
    $controller = new \App\Http\Controllers\Api\DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');
    $token = $method->invoke($controller, $this->device);

    \Illuminate\Support\Facades\Event::fake();

    $result = $this->manager->handleHello(1, [
        'type' => 'hello',
        'access_token' => $token,
    ]);

    $sessionId = $this->manager->getSessionId($this->device->id);
    expect($sessionId)->not->toBeNull();
    expect($sessionId)->toBe($result['response']['session_id']);
});
