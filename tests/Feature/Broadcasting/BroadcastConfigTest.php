<?php

use App\Events\ChiefCommandDispatched;
use App\Events\ChiefMessageReceived;
use App\Events\DeviceConnected;
use App\Events\DeviceDisconnected;
use App\Events\DeviceTokenRevoked;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/*
|--------------------------------------------------------------------------
| ShouldBroadcastNow Enforcement
|--------------------------------------------------------------------------
| All broadcast events MUST implement ShouldBroadcastNow (not queued
| ShouldBroadcast) so broadcasts reach Reverb immediately without
| requiring a queue worker.
*/

describe('ShouldBroadcastNow', function () {
    it('ChiefCommandDispatched implements ShouldBroadcastNow', function () {
        $event = new ChiefCommandDispatched(1, 1, ['type' => 'ping']);
        expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
    });

    it('ChiefMessageReceived implements ShouldBroadcastNow', function () {
        $event = new ChiefMessageReceived(1, 1, ['type' => 'pong']);
        expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
    });

    it('DeviceConnected implements ShouldBroadcastNow', function () {
        $event = new DeviceConnected(1, 1);
        expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
    });

    it('DeviceDisconnected implements ShouldBroadcastNow', function () {
        $event = new DeviceDisconnected(1, 1);
        expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
    });

    it('DeviceTokenRevoked implements ShouldBroadcastNow', function () {
        $event = new DeviceTokenRevoked(1, 1);
        expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
    });
});

/*
|--------------------------------------------------------------------------
| Channel Configuration
|--------------------------------------------------------------------------
| Verify each event broadcasts on the correct channel(s).
*/

describe('Channel Configuration', function () {
    it('ChiefCommandDispatched broadcasts on private chief-server channel', function () {
        $event = new ChiefCommandDispatched(42, 1, ['type' => 'start_run']);

        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1);
        expect($channels[0]->name)->toBe('private-chief-server.42');
    });

    it('ChiefMessageReceived broadcasts on private device channel', function () {
        $event = new ChiefMessageReceived(42, 1, ['type' => 'claude_output']);

        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1);
        expect($channels[0]->name)->toBe('private-device.42');
    });

    it('DeviceConnected broadcasts on private user channel', function () {
        $event = new DeviceConnected(42, 7);

        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1);
        expect($channels[0]->name)->toBe('private-user.7');
    });

    it('DeviceDisconnected broadcasts on private user channel', function () {
        $event = new DeviceDisconnected(42, 7);

        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1);
        expect($channels[0]->name)->toBe('private-user.7');
    });

    it('DeviceTokenRevoked broadcasts on both user and device channels', function () {
        $event = new DeviceTokenRevoked(42, 7);

        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(2);
        expect($channels[0]->name)->toBe('private-user.7');
        expect($channels[1]->name)->toBe('private-device.42');
    });
});

/*
|--------------------------------------------------------------------------
| Event Names
|--------------------------------------------------------------------------
| Verify broadcastAs() returns the event names the frontend expects.
*/

describe('Event Names', function () {
    it('ChiefCommandDispatched broadcasts as chief.command', function () {
        $event = new ChiefCommandDispatched(1, 1, ['type' => 'ping']);
        expect($event->broadcastAs())->toBe('chief.command');
    });

    it('ChiefMessageReceived broadcasts as chief.message', function () {
        $event = new ChiefMessageReceived(1, 1, ['type' => 'pong']);
        expect($event->broadcastAs())->toBe('chief.message');
    });

    it('DeviceConnected broadcasts as device.connected', function () {
        $event = new DeviceConnected(1, 1);
        expect($event->broadcastAs())->toBe('device.connected');
    });

    it('DeviceDisconnected broadcasts as device.disconnected', function () {
        $event = new DeviceDisconnected(1, 1);
        expect($event->broadcastAs())->toBe('device.disconnected');
    });

    it('DeviceTokenRevoked broadcasts as device.token.revoked', function () {
        $event = new DeviceTokenRevoked(1, 1);
        expect($event->broadcastAs())->toBe('device.token.revoked');
    });
});

/*
|--------------------------------------------------------------------------
| Payload Shape
|--------------------------------------------------------------------------
| Verify broadcastWith() returns the payload structure the frontend expects.
*/

describe('Payload Shape', function () {
    it('ChiefCommandDispatched payload is the raw command array', function () {
        $command = ['type' => 'start_run', 'payload' => ['project_slug' => 'my-project']];
        $event = new ChiefCommandDispatched(1, 1, $command);

        expect($event->broadcastWith())->toBe($command);
    });

    it('ChiefMessageReceived payload includes device_id, type, payload, and message', function () {
        $message = ['type' => 'claude_output', 'payload' => ['text' => 'Hello']];
        $event = new ChiefMessageReceived(42, 1, $message);

        $payload = $event->broadcastWith();
        expect($payload)->toHaveKeys(['device_id', 'type', 'payload', 'message']);
        expect($payload['device_id'])->toBe(42);
        expect($payload['type'])->toBe('claude_output');
        expect($payload['payload'])->toBe(['text' => 'Hello']);
        expect($payload['message'])->toBe($message);
    });

    it('ChiefMessageReceived defaults type to unknown when missing', function () {
        $event = new ChiefMessageReceived(1, 1, ['payload' => ['data' => 'test']]);

        $payload = $event->broadcastWith();
        expect($payload['type'])->toBe('unknown');
    });

    it('ChiefMessageReceived defaults payload to null when missing', function () {
        $event = new ChiefMessageReceived(1, 1, ['type' => 'heartbeat']);

        $payload = $event->broadcastWith();
        expect($payload['payload'])->toBeNull();
    });
});
