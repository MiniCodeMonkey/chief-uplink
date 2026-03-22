<?php

use App\Events\DeviceStatusChanged;
use App\Models\Device;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the team devices channel', function () {
    $device = Device::factory()->connected()->create();

    $event = new DeviceStatusChanged($device);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe("private-team.{$device->team_id}.devices");
});

it('includes device id and status in broadcast data', function () {
    $device = Device::factory()->connected()->create();

    $event = new DeviceStatusChanged($device);

    $data = $event->broadcastWith();

    expect($data)->toHaveKeys(['id', 'connected', 'last_seen_at']);
    expect($data['id'])->toBe($device->id);
    expect($data['connected'])->toBeTrue();
});

it('authorizes team member to listen on team devices channel', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam();

    $authorized = $user->teams()->where('teams.id', $team->id)->exists();

    expect($authorized)->toBeTrue();
});

it('rejects non-member from team devices channel', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherTeam = $otherUser->currentTeam();

    $authorized = $user->teams()->where('teams.id', $otherTeam->id)->exists();

    expect($authorized)->toBeFalse();
});
