<?php

use App\Models\CachedProjectState;
use App\Models\CloudDeployment;
use App\Models\DeviceAuthorization;
use App\Models\LogCache;
use App\Models\RunHistory;
use App\Models\User;

test('device authorization belongs to user', function () {
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->for($user)->create();

    expect($device->user->id)->toBe($user->id);
});

test('device authorization has cached project states', function () {
    $device = DeviceAuthorization::factory()->create();
    CachedProjectState::factory()->for($device)->create();

    expect($device->cachedProjectStates)->toHaveCount(1);
});

test('device authorization has run history', function () {
    $device = DeviceAuthorization::factory()->create();
    RunHistory::factory()->for($device)->create();

    expect($device->runHistory)->toHaveCount(1);
});

test('device authorization has log cache', function () {
    $device = DeviceAuthorization::factory()->create();
    LogCache::factory()->for($device)->create();

    expect($device->logCache)->toHaveCount(1);
});

test('device authorization has one cloud deployment', function () {
    $device = DeviceAuthorization::factory()->create();
    CloudDeployment::factory()->create(['device_authorization_id' => $device->id, 'user_id' => $device->user_id]);

    expect($device->cloudDeployment)->not->toBeNull();
});

test('device authorization casts is_online to boolean', function () {
    $device = DeviceAuthorization::factory()->online()->create();
    expect($device->is_online)->toBeBool();
    expect($device->is_online)->toBeTrue();
});

test('device authorization casts last_connected_at to datetime', function () {
    $device = DeviceAuthorization::factory()->online()->create();
    expect($device->last_connected_at)->toBeInstanceOf(\DateTimeInterface::class);
});

test('device authorization casts revoked_at to datetime', function () {
    $device = DeviceAuthorization::factory()->revoked()->create();
    expect($device->revoked_at)->toBeInstanceOf(\DateTimeInterface::class);
});

test('isRevoked returns true when device is revoked', function () {
    $device = DeviceAuthorization::factory()->revoked()->create();
    expect($device->isRevoked())->toBeTrue();
});

test('isRevoked returns false when device is not revoked', function () {
    $device = DeviceAuthorization::factory()->online()->create();
    expect($device->isRevoked())->toBeFalse();
});

test('device factory online state', function () {
    $device = DeviceAuthorization::factory()->online()->create();

    expect($device->is_online)->toBeTrue();
    expect($device->last_connected_at)->not->toBeNull();
});

test('device factory offline state', function () {
    $device = DeviceAuthorization::factory()->offline()->create();

    expect($device->is_online)->toBeFalse();
});

test('device factory revoked state', function () {
    $device = DeviceAuthorization::factory()->revoked()->create();

    expect($device->is_online)->toBeFalse();
    expect($device->revoked_at)->not->toBeNull();
});
