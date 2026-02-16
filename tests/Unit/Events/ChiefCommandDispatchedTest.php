<?php

use App\Events\ChiefCommandDispatched;
use Illuminate\Broadcasting\PrivateChannel;

test('it broadcasts on the correct private channel', function () {
    $event = new ChiefCommandDispatched(
        deviceId: 42,
        userId: 1,
        command: ['type' => 'run', 'payload' => ['project' => 'test']],
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-chief-server.42');
});

test('it uses chief.command as the broadcast event name', function () {
    $event = new ChiefCommandDispatched(
        deviceId: 1,
        userId: 1,
        command: ['type' => 'run'],
    );

    expect($event->broadcastAs())->toBe('chief.command');
});

test('it broadcasts the command array as the payload', function () {
    $command = [
        'type' => 'run',
        'payload' => [
            'project' => '/home/user/my-project',
            'prompt' => 'fix the bug',
        ],
    ];

    $event = new ChiefCommandDispatched(
        deviceId: 1,
        userId: 1,
        command: $command,
    );

    expect($event->broadcastWith())->toBe($command);
});

test('it implements ShouldBroadcast', function () {
    $event = new ChiefCommandDispatched(
        deviceId: 1,
        userId: 1,
        command: ['type' => 'ping'],
    );

    expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

test('it stores constructor properties correctly', function () {
    $event = new ChiefCommandDispatched(
        deviceId: 99,
        userId: 7,
        command: ['type' => 'stop'],
    );

    expect($event->deviceId)->toBe(99);
    expect($event->userId)->toBe(7);
    expect($event->command)->toBe(['type' => 'stop']);
});
