<?php

use App\Events\DeviceStreamEvent;
use App\Models\Device;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the private device channel', function () {
    $device = Device::factory()->create();
    $event = new DeviceStreamEvent($device, ['chunk' => 'hello']);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-device.{$device->id}");
});

it('broadcasts event data', function () {
    $device = Device::factory()->create();
    $data = ['chunk' => 'hello world'];
    $event = new DeviceStreamEvent($device, $data);

    expect($event->broadcastWith())->toBe($data);
});
