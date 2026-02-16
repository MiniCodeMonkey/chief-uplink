<?php

use App\Models\CloudDeployment;
use App\Models\DeviceAuthorization;
use App\Models\User;

test('cloud deployment belongs to user', function () {
    $user = User::factory()->create();
    $deployment = CloudDeployment::factory()->for($user)->create();

    expect($deployment->user->id)->toBe($user->id);
});

test('cloud deployment belongs to device authorization', function () {
    $device = DeviceAuthorization::factory()->create();
    $deployment = CloudDeployment::factory()->create([
        'device_authorization_id' => $device->id,
        'user_id' => $device->user_id,
    ]);

    expect($deployment->deviceAuthorization->id)->toBe($device->id);
});

test('cloud deployment encrypts provider_api_key', function () {
    $key = 'my-secret-api-key-12345';
    $deployment = CloudDeployment::factory()->create(['provider_api_key' => $key]);

    // Verify decrypted value matches
    expect($deployment->provider_api_key)->toBe($key);

    // Verify raw database value is encrypted (not plaintext)
    $raw = \DB::table('cloud_deployments')
        ->where('id', $deployment->id)
        ->value('provider_api_key');
    expect($raw)->not->toBe($key);
});

test('cloud deployment casts monthly_cost to decimal', function () {
    $deployment = CloudDeployment::factory()->create(['monthly_cost' => '5.49']);

    expect($deployment->monthly_cost)->toBe('5.49');
});

test('cloud deployment casts setup_token_expires_at to datetime', function () {
    $deployment = CloudDeployment::factory()->provisioning()->create();

    expect($deployment->setup_token_expires_at)->toBeInstanceOf(\DateTimeInterface::class);
});

test('cloud deployment factory provisioning state', function () {
    $deployment = CloudDeployment::factory()->provisioning()->create();

    expect($deployment->status)->toBe('provisioning');
    expect($deployment->ip_address)->toBeNull();
    expect($deployment->setup_token)->not->toBeNull();
});

test('cloud deployment factory destroyed state', function () {
    $deployment = CloudDeployment::factory()->destroyed()->create();

    expect($deployment->status)->toBe('destroyed');
});

test('cloud deployment factory hetzner state', function () {
    $deployment = CloudDeployment::factory()->hetzner()->create();

    expect($deployment->provider)->toBe('hetzner');
    expect($deployment->region)->toBe('nbg1');
});

test('cloud deployment factory digitalocean state', function () {
    $deployment = CloudDeployment::factory()->digitalocean()->create();

    expect($deployment->provider)->toBe('digitalocean');
    expect($deployment->region)->toBe('nyc1');
});
