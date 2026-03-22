<?php

use App\Models\DeviceCode;
use App\Models\Team;
use App\Models\User;

it('has correct fillable attributes', function () {
    $deviceCode = DeviceCode::factory()->create([
        'device_code' => 'abc123',
        'user_code' => 'WXYZ1234',
        'device_name' => 'My MacBook',
    ]);

    expect($deviceCode->device_code)->toBe('abc123')
        ->and($deviceCode->user_code)->toBe('WXYZ1234')
        ->and($deviceCode->device_name)->toBe('My MacBook');
});

it('casts expires_at as datetime', function () {
    $deviceCode = DeviceCode::factory()->create();

    expect($deviceCode->expires_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('casts approved_at as datetime', function () {
    $deviceCode = DeviceCode::factory()->approved()->create();

    expect($deviceCode->approved_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('belongs to a user (nullable)', function () {
    $user = User::factory()->create();
    $deviceCode = DeviceCode::factory()->create(['user_id' => $user->id]);

    expect($deviceCode->user)->toBeInstanceOf(User::class)
        ->and($deviceCode->user->id)->toBe($user->id);
});

it('allows null user_id', function () {
    $deviceCode = DeviceCode::factory()->create(['user_id' => null]);

    expect($deviceCode->user_id)->toBeNull()
        ->and($deviceCode->user)->toBeNull();
});

it('belongs to a team (nullable)', function () {
    $team = Team::factory()->create();
    $deviceCode = DeviceCode::factory()->create(['team_id' => $team->id]);

    expect($deviceCode->team)->toBeInstanceOf(Team::class)
        ->and($deviceCode->team->id)->toBe($team->id);
});

it('allows null team_id', function () {
    $deviceCode = DeviceCode::factory()->create(['team_id' => null]);

    expect($deviceCode->team_id)->toBeNull()
        ->and($deviceCode->team)->toBeNull();
});

it('has approved factory state', function () {
    $deviceCode = DeviceCode::factory()->approved()->create();

    expect($deviceCode->approved_at)->not->toBeNull();
});
