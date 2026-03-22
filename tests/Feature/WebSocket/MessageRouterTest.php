<?php

use App\Contracts\WebSocketConnection;
use App\Models\Device;
use App\Services\WebSocket\MessageRouter;

// --- resolveCategory ---

it('resolves state category for state.* types', function () {
    $router = app(MessageRouter::class);

    expect($router->resolveCategory('state.update'))->toBe('state')
        ->and($router->resolveCategory('state.sync'))->toBe('state');
});

it('resolves state category for slug-based state types', function () {
    $router = app(MessageRouter::class);

    expect($router->resolveCategory('sync'))->toBe('state')
        ->and($router->resolveCategory('prd-updated'))->toBe('state')
        ->and($router->resolveCategory('run-completed'))->toBe('state')
        ->and($router->resolveCategory('device-heartbeat'))->toBe('state');
});

it('resolves control category', function () {
    $router = app(MessageRouter::class);

    expect($router->resolveCategory('ack'))->toBe('control')
        ->and($router->resolveCategory('error'))->toBe('control')
        ->and($router->resolveCategory('welcome'))->toBe('control');
});

it('resolves cmd category', function () {
    $router = app(MessageRouter::class);

    expect($router->resolveCategory('prd-create'))->toBe('cmd')
        ->and($router->resolveCategory('run-start'))->toBe('cmd')
        ->and($router->resolveCategory('settings-get'))->toBe('cmd');
});

it('resolves unknown category for unrecognized types', function () {
    $router = app(MessageRouter::class);

    expect($router->resolveCategory('something.weird'))->toBe('unknown');
});

// --- cmd routing ---

it('routes cmd messages to command handler', function () {
    $device = Device::factory()->create();

    $conn = Mockery::mock(WebSocketConnection::class);
    $conn->shouldReceive('id')->andReturn(1);
    $conn->shouldReceive('send')->once()->withArgs(function (array $data) {
        return $data['type'] === 'ack'
            && $data['payload']['status'] === 'received';
    });

    $router = app(MessageRouter::class);
    $router->route($conn, $device, [
        'type' => 'prd-create',
        'id' => '550e8400-e29b-41d4-a716-446655440031',
        'payload' => [
            'project_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'title' => 'Test PRD',
            'content' => 'Test content',
        ],
    ]);
});

it('routes state.update to state handler', function () {
    $device = Device::factory()->create(['chief_version' => '1.0.0']);

    $conn = Mockery::mock(WebSocketConnection::class);
    $conn->shouldReceive('id')->andReturn(1);
    $conn->shouldReceive('send')->once()->withArgs(function (array $data) {
        return $data['type'] === 'state.ack';
    });

    $router = app(MessageRouter::class);
    $router->route($conn, $device, [
        'type' => 'state.update',
        'payload' => ['chief_version' => '2.0.0'],
    ]);

    expect($device->fresh()->chief_version)->toBe('2.0.0');
});

it('routes state.sync to state handler', function () {
    $device = Device::factory()->create();

    $conn = Mockery::mock(WebSocketConnection::class);
    $conn->shouldReceive('id')->andReturn(1);
    $conn->shouldReceive('send')->once()->withArgs(function (array $data) {
        return $data['type'] === 'state.current'
            && isset($data['payload']['device_id']);
    });

    $router = app(MessageRouter::class);
    $router->route($conn, $device, ['type' => 'state.sync']);
});

it('routes ack to control handler', function () {
    $device = Device::factory()->create();

    $conn = Mockery::mock(WebSocketConnection::class);
    $conn->shouldReceive('id')->andReturn(1);

    $router = app(MessageRouter::class);
    $router->route($conn, $device, [
        'type' => 'ack',
        'payload' => ['ref_id' => '550e8400-e29b-41d4-a716-446655440000'],
    ]);

    // No exception means it was routed correctly
    expect(true)->toBeTrue();
});

it('routes error to control handler', function () {
    $device = Device::factory()->create();

    $conn = Mockery::mock(WebSocketConnection::class);
    $conn->shouldReceive('id')->andReturn(1);

    $router = app(MessageRouter::class);
    $router->route($conn, $device, [
        'type' => 'error',
        'payload' => ['message' => 'something went wrong'],
    ]);

    expect(true)->toBeTrue();
});

it('sends error for unknown message types', function () {
    $device = Device::factory()->create();

    $conn = Mockery::mock(WebSocketConnection::class);
    $conn->shouldReceive('id')->andReturn(1);
    $conn->shouldReceive('send')->once()->withArgs(function (array $data) {
        return $data['type'] === 'error'
            && $data['payload']['code'] === 'unknown_message_type';
    });

    $router = app(MessageRouter::class);
    $router->route($conn, $device, ['type' => 'something.weird']);
});
