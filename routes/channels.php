<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('device.{deviceId}', function ($user, $deviceId) {
    return $user->deviceAuthorizations()
        ->where('id', $deviceId)
        ->whereNull('revoked_at')
        ->exists();
});

Broadcast::channel('chief-server.{deviceId}', function ($user, $deviceId) {
    return $user->deviceAuthorizations()
        ->where('id', $deviceId)
        ->whereNull('revoked_at')
        ->exists();
});
