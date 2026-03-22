<?php

use App\Contracts\WebSocketConnection;
use App\Models\Device;
use App\Services\WebSocket\MessageRouter;

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
        'payload' => ['command_id' => null],
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
