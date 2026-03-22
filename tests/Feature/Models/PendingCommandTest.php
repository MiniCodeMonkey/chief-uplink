<?php

use App\Models\Device;
use App\Models\PendingCommand;

it('belongs to a device', function () {
    $command = PendingCommand::factory()->create();

    expect($command->device)->toBeInstanceOf(Device::class);
});

it('casts payload as array', function () {
    $command = PendingCommand::factory()->create([
        'payload' => ['action' => 'sync', 'target' => 'all'],
    ]);

    expect($command->fresh()->payload)
        ->toBeArray()
        ->toBe(['action' => 'sync', 'target' => 'all']);
});

it('can be created with factory', function () {
    $command = PendingCommand::factory()->create();

    expect($command)
        ->toBeInstanceOf(PendingCommand::class)
        ->type->toBeString()
        ->payload->toBeArray();
});

it('is accessible from device relationship', function () {
    $device = Device::factory()->create();
    PendingCommand::factory()->count(3)->create(['device_id' => $device->id]);

    expect($device->pendingCommands)->toHaveCount(3);
});
