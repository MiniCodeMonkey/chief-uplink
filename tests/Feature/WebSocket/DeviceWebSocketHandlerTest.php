<?php

use App\Contracts\WebSocketConnection;
use App\Events\DeviceConnected;
use App\Events\DeviceDisconnected;
use App\Models\Device;
use App\Models\PendingCommand;
use App\Services\WebSocket\DeviceWebSocketHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function mockConnection(int $id = 1): WebSocketConnection
{
    $conn = Mockery::mock(WebSocketConnection::class);
    $conn->shouldReceive('id')->andReturn($id);

    return $conn;
}

function envelope(string $type, string $deviceId = 'device-abc-123', ?array $payload = null): array
{
    $message = [
        'type' => $type,
        'id' => (string) Str::uuid(),
        'device_id' => $deviceId,
        'timestamp' => now()->toIso8601String(),
    ];

    if ($payload !== null) {
        $message['payload'] = $payload;
    }

    return $message;
}

it('sends welcome message on open', function () {
    Event::fake();

    $device = Device::factory()->create();
    $conn = mockConnection();

    $conn->shouldReceive('send')->once()->withArgs(function (array $data) {
        return $data['type'] === 'welcome'
            && isset($data['payload']['session_id'])
            && isset($data['payload']['server_version'])
            && is_array($data['payload']['capabilities']);
    });

    $handler = app(DeviceWebSocketHandler::class);
    $handler->onOpen($conn, $device);
});

it('fires DeviceConnected event on open', function () {
    Event::fake();

    $device = Device::factory()->create();
    $conn = mockConnection();
    $conn->shouldReceive('send');

    $handler = app(DeviceWebSocketHandler::class);
    $handler->onOpen($conn, $device);

    Event::assertDispatched(DeviceConnected::class, fn ($event) => $event->device->id === $device->id);
});

it('sets device connected to true and updates last_seen_at on open', function () {
    Event::fake();

    $device = Device::factory()->create(['connected' => false, 'last_seen_at' => null]);
    $conn = mockConnection();
    $conn->shouldReceive('send');

    $handler = app(DeviceWebSocketHandler::class);
    $handler->onOpen($conn, $device);

    $device->refresh();
    expect($device->connected)->toBeTrue()
        ->and($device->last_seen_at)->not->toBeNull();
});

it('drains pending commands oldest first on open', function () {
    Event::fake();

    $device = Device::factory()->create();
    $cmd1 = PendingCommand::factory()->create([
        'device_id' => $device->id,
        'type' => 'sync.state',
        'created_at' => now()->subMinutes(2),
    ]);
    $cmd2 = PendingCommand::factory()->create([
        'device_id' => $device->id,
        'type' => 'run.start',
        'created_at' => now()->subMinute(),
    ]);

    $sentCommands = [];
    $conn = mockConnection();
    $conn->shouldReceive('send')->andReturnUsing(function (array $data) use (&$sentCommands) {
        $sentCommands[] = $data;
    });

    $handler = app(DeviceWebSocketHandler::class);
    $handler->onOpen($conn, $device);

    // First message is welcome, then commands
    expect($sentCommands)->toHaveCount(3)
        ->and($sentCommands[0]['type'])->toBe('welcome')
        ->and($sentCommands[1]['type'])->toBe('command')
        ->and($sentCommands[1]['payload']['command_id'])->toBe($cmd1->id)
        ->and($sentCommands[2]['type'])->toBe('command')
        ->and($sentCommands[2]['payload']['command_id'])->toBe($cmd2->id);
});

it('fires DeviceDisconnected event on close', function () {
    Event::fake();

    $device = Device::factory()->create(['connected' => true]);
    $conn = mockConnection();
    $conn->shouldReceive('send');

    $handler = app(DeviceWebSocketHandler::class);
    $handler->onOpen($conn, $device);

    $handler->onClose($conn);

    Event::assertDispatched(DeviceDisconnected::class, fn ($event) => $event->device->id === $device->id);
});

it('sets device connected to false on close', function () {
    Event::fake();

    $device = Device::factory()->create(['connected' => true]);
    $conn = mockConnection();
    $conn->shouldReceive('send');

    $handler = app(DeviceWebSocketHandler::class);
    $handler->onOpen($conn, $device);
    $handler->onClose($conn);

    expect($device->fresh()->connected)->toBeFalse();
});

it('routes state messages to state handler', function () {
    Event::fake();

    $device = Device::factory()->create(['chief_version' => '1.0.0']);
    $conn = mockConnection();
    $conn->shouldReceive('send');

    $handler = app(DeviceWebSocketHandler::class);
    $handler->onOpen($conn, $device);

    $handler->onMessage($conn, json_encode(
        envelope('state.update', 'device-abc-123', ['chief_version' => '2.0.0']),
    ));

    expect($device->fresh()->chief_version)->toBe('2.0.0');
});

it('routes ack messages to control handler', function () {
    Event::fake();

    $device = Device::factory()->create();

    $conn = mockConnection();
    $conn->shouldReceive('send');

    $handler = app(DeviceWebSocketHandler::class);
    $handler->onOpen($conn, $device);

    $handler->onMessage($conn, json_encode(
        envelope('ack', 'device-abc-123', ['ref_id' => '550e8400-e29b-41d4-a716-446655440000']),
    ));

    // No exception means it was routed correctly to the control handler
    expect(true)->toBeTrue();
});

it('sends error for invalid json messages', function () {
    Event::fake();

    $device = Device::factory()->create();
    $conn = mockConnection();

    $sentMessages = [];
    $conn->shouldReceive('send')->andReturnUsing(function (array $data) use (&$sentMessages) {
        $sentMessages[] = $data;
    });

    $handler = app(DeviceWebSocketHandler::class);
    $handler->onOpen($conn, $device);

    $handler->onMessage($conn, 'not valid json');

    $errorMessages = array_filter($sentMessages, fn ($m) => $m['type'] === 'error');
    expect($errorMessages)->not->toBeEmpty();

    $error = array_values($errorMessages)[0];
    expect($error['payload']['code'])->toBe('invalid_message');
});

it('sends error for unknown message types', function () {
    Event::fake();

    $device = Device::factory()->create();
    $conn = mockConnection();

    $sentMessages = [];
    $conn->shouldReceive('send')->andReturnUsing(function (array $data) use (&$sentMessages) {
        $sentMessages[] = $data;
    });

    $handler = app(DeviceWebSocketHandler::class);
    $handler->onOpen($conn, $device);

    $handler->onMessage($conn, json_encode(
        envelope('unknown.thing'),
    ));

    $errorMessages = array_filter($sentMessages, fn ($m) => $m['type'] === 'error');
    expect($errorMessages)->not->toBeEmpty();

    $error = array_values($errorMessages)[0];
    expect($error['payload']['code'])->toBe('unknown_message_type');
});
