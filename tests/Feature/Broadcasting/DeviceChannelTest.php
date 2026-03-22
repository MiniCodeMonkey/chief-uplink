<?php

use App\Models\Device;
use App\Models\User;

it('authorizes user who belongs to the device team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);

    $authorized = $user->teams()->where('teams.id', $device->team_id)->exists();

    expect($authorized)->toBeTrue();
});

it('rejects user who does not belong to the device team', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $team = $otherUser->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);

    $authorized = $user->teams()->where('teams.id', $device->team_id)->exists();

    expect($authorized)->toBeFalse();
});

it('rejects when device does not exist', function () {
    $user = User::factory()->create();

    $device = Device::find(999);

    expect($device)->toBeNull();
});
