<?php

use App\Events\DeviceStateUpdated;
use App\Models\Device;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the private device channel', function () {
    $device = Device::factory()->create();
    $event = new DeviceStateUpdated($device, ['connected' => true]);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-device.{$device->id}");
});

it('broadcasts state data', function () {
    $device = Device::factory()->create();
    $state = ['connected' => true, 'chief_version' => '1.0.0'];
    $event = new DeviceStateUpdated($device, $state);

    expect($event->broadcastWith())->toBe($state);
});
