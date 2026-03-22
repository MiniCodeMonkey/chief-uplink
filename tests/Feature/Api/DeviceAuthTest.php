<?php

use App\Models\Device;
use App\Models\DeviceCode;
use App\Models\User;

describe('POST /api/auth/device/request', function () {
    it('returns a device code with expected fields', function () {
        $response = $this->postJson('/api/auth/device/request', [
            'device_name' => 'My MacBook',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'device_code',
                'user_code',
                'verify_url',
                'expires_in',
                'interval',
            ]);

        expect($response->json('expires_in'))->toBe(900);
        expect($response->json('interval'))->toBe(5);
        expect($response->json('device_code'))->toHaveLength(40);
        expect($response->json('user_code'))->toHaveLength(8);
    });

    it('creates a device code record in the database', function () {
        $this->postJson('/api/auth/device/request', [
            'device_name' => 'My MacBook',
        ]);

        $this->assertDatabaseHas('device_codes', [
            'device_name' => 'My MacBook',
        ]);

        $deviceCode = DeviceCode::first();
        expect($deviceCode->expires_at->isFuture())->toBeTrue();
        expect($deviceCode->approved_at)->toBeNull();
        expect($deviceCode->user_id)->toBeNull();
        expect($deviceCode->team_id)->toBeNull();
    });

    it('requires device_name', function () {
        $response = $this->postJson('/api/auth/device/request', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_name']);
    });
});

describe('POST /api/auth/device/verify', function () {
    it('returns 202 pending when device code is not yet approved', function () {
        $deviceCode = DeviceCode::factory()->create();

        $response = $this->postJson('/api/auth/device/verify', [
            'device_code' => $deviceCode->device_code,
        ]);

        $response->assertStatus(202)
            ->assertJson(['status' => 'pending']);
    });

    it('returns 200 with tokens when device code is approved', function () {
        $user = User::factory()->create();
        $team = $user->currentTeam();

        $deviceCode = DeviceCode::factory()->approved()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        $response = $this->postJson('/api/auth/device/verify', [
            'device_code' => $deviceCode->device_code,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'expires_in',
                'device_id',
            ]);

        expect($response->json('expires_in'))->toBe(30 * 24 * 60 * 60);
    });

    it('creates a device record when approved', function () {
        $user = User::factory()->create();
        $team = $user->currentTeam();

        $deviceCode = DeviceCode::factory()->approved()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'device_name' => 'Test Device',
        ]);

        $response = $this->postJson('/api/auth/device/verify', [
            'device_code' => $deviceCode->device_code,
        ]);

        $device = Device::find($response->json('device_id'));
        expect($device)->not->toBeNull();
        expect($device->name)->toBe('Test Device');
        expect($device->team_id)->toBe($team->id);
    });

    it('stores access token as SHA-256 hash', function () {
        $user = User::factory()->create();
        $team = $user->currentTeam();

        $deviceCode = DeviceCode::factory()->approved()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        $response = $this->postJson('/api/auth/device/verify', [
            'device_code' => $deviceCode->device_code,
        ]);

        $plainToken = $response->json('access_token');
        $device = Device::find($response->json('device_id'));

        // The stored token should be a SHA-256 hash, not the plain token
        expect($device->access_token)->not->toBe($plainToken);
        expect($device->access_token)->toBe(hash('sha256', $plainToken));
    });

    it('deletes the device code after successful verification', function () {
        $user = User::factory()->create();
        $team = $user->currentTeam();

        $deviceCode = DeviceCode::factory()->approved()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        $this->postJson('/api/auth/device/verify', [
            'device_code' => $deviceCode->device_code,
        ]);

        $this->assertDatabaseMissing('device_codes', [
            'id' => $deviceCode->id,
        ]);
    });

    it('returns 410 when device code has expired', function () {
        $deviceCode = DeviceCode::factory()->create([
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/auth/device/verify', [
            'device_code' => $deviceCode->device_code,
        ]);

        $response->assertStatus(410)
            ->assertJson(['error' => 'expired_token']);
    });

    it('returns 404 for invalid device code', function () {
        $response = $this->postJson('/api/auth/device/verify', [
            'device_code' => 'nonexistent-code',
        ]);

        $response->assertNotFound()
            ->assertJson(['error' => 'invalid_device_code']);
    });

    it('requires device_code parameter', function () {
        $response = $this->postJson('/api/auth/device/verify', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_code']);
    });

    it('can look up the created device by plain access token', function () {
        $user = User::factory()->create();
        $team = $user->currentTeam();

        $deviceCode = DeviceCode::factory()->approved()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        $response = $this->postJson('/api/auth/device/verify', [
            'device_code' => $deviceCode->device_code,
        ]);

        $plainToken = $response->json('access_token');
        $found = Device::findByToken($plainToken);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($response->json('device_id'));
    });
});
