<?php

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('device.{deviceId}', function (User $user, int $deviceId) {
    $device = Device::find($deviceId);

    return $device && $user->teams()->where('teams.id', $device->team_id)->exists();
});

Broadcast::channel('team.{teamId}.devices', function (User $user, int $teamId) {
    return $user->teams()->where('teams.id', $teamId)->exists();
});
