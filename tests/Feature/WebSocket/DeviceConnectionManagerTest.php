<?php

use App\Contracts\WebSocketConnection;
use App\Services\WebSocket\DeviceConnectionManager;

function makeConnection(int $id): WebSocketConnection
{
    $connection = Mockery::mock(WebSocketConnection::class);
    $connection->shouldReceive('id')->andReturn($id);

    return $connection;
}

it('tracks a connection for a device', function () {
    $manager = new DeviceConnectionManager;
    $conn = makeConnection(1);

    $manager->add($conn, 42);

    expect($manager->count())->toBe(1)
        ->and($manager->isConnected(42))->toBeTrue()
        ->and($manager->getDeviceId($conn))->toBe(42);
});

it('retrieves a connection by device id', function () {
    $manager = new DeviceConnectionManager;
    $conn = makeConnection(1);

    $manager->add($conn, 42);

    expect($manager->getConnectionForDevice(42))->toBe($conn);
});

it('returns null for unknown device id', function () {
    $manager = new DeviceConnectionManager;

    expect($manager->getConnectionForDevice(999))->toBeNull();
});

it('removes a connection and returns the device id', function () {
    $manager = new DeviceConnectionManager;
    $conn = makeConnection(1);

    $manager->add($conn, 42);
    $deviceId = $manager->remove($conn);

    expect($deviceId)->toBe(42)
        ->and($manager->count())->toBe(0)
        ->and($manager->isConnected(42))->toBeFalse();
});

it('returns null when removing an unknown connection', function () {
    $manager = new DeviceConnectionManager;
    $conn = makeConnection(1);

    expect($manager->remove($conn))->toBeNull();
});

it('tracks multiple connections', function () {
    $manager = new DeviceConnectionManager;
    $conn1 = makeConnection(1);
    $conn2 = makeConnection(2);

    $manager->add($conn1, 10);
    $manager->add($conn2, 20);

    expect($manager->count())->toBe(2)
        ->and($manager->isConnected(10))->toBeTrue()
        ->and($manager->isConnected(20))->toBeTrue();
});
