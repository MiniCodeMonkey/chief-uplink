<?php

use App\Models\Device;
use App\Models\Team;

it('belongs to a team', function () {
    $device = Device::factory()->create();

    expect($device->team)->toBeInstanceOf(Team::class);
});

it('has correct fillable attributes', function () {
    $device = Device::factory()->create([
        'name' => 'Test MacBook',
        'os' => 'darwin',
        'arch' => 'arm64',
        'chief_version' => '1.0.0',
        'connected' => true,
    ]);

    expect($device->name)->toBe('Test MacBook')
        ->and($device->os)->toBe('darwin')
        ->and($device->arch)->toBe('arm64')
        ->and($device->chief_version)->toBe('1.0.0')
        ->and($device->connected)->toBeTrue();
});

it('casts token_expires_at as datetime', function () {
    $device = Device::factory()->create([
        'token_expires_at' => '2026-12-31 23:59:59',
    ]);

    expect($device->token_expires_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('casts last_seen_at as datetime', function () {
    $device = Device::factory()->create([
        'last_seen_at' => now(),
    ]);

    expect($device->last_seen_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('casts connected as boolean', function () {
    $device = Device::factory()->create(['connected' => 1]);

    expect($device->connected)->toBeTrue();
});

it('hides access_token and refresh_token_hash in serialization', function () {
    $device = Device::factory()->create();
    $array = $device->toArray();

    expect($array)->not->toHaveKey('access_token')
        ->and($array)->not->toHaveKey('refresh_token_hash');
});

it('allows nullable managed_server_id', function () {
    $device = Device::factory()->create(['managed_server_id' => null]);

    expect($device->managed_server_id)->toBeNull();
});

it('finds a device by plain-text access token', function () {
    $plainToken = 'test-plain-token-for-device-lookup';
    $hash = hash('sha256', $plainToken);

    $device = Device::factory()->create(['access_token' => $hash]);

    $found = Device::findByToken($plainToken);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($device->id);
});

it('returns null when token is not found', function () {
    Device::factory()->create();

    $found = Device::findByToken('nonexistent-token');

    expect($found)->toBeNull();
});

it('stores access_token as 64-char hash', function () {
    $plainToken = 'my-secret-token';
    $hash = hash('sha256', $plainToken);

    $device = Device::factory()->create(['access_token' => $hash]);

    expect(strlen($device->getRawOriginal('access_token')))->toBe(64);
});

it('stores refresh_token_hash as 64-char hash', function () {
    $device = Device::factory()->create();

    expect(strlen($device->refresh_token_hash))->toBe(64);
});

it('has connected factory state', function () {
    $device = Device::factory()->connected()->create();

    expect($device->connected)->toBeTrue()
        ->and($device->last_seen_at)->not->toBeNull();
});
