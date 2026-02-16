<?php

use App\Http\Controllers\Api\DeviceOAuthController;
use App\Models\DeviceAuthorization;
use App\Models\User;

test('generateAccessToken creates valid token', function () {
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->for($user)->online()->create();

    // Use reflection to access private method
    $controller = new DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');

    $token = $method->invoke($controller, $device);

    expect($token)->toBeString();
    expect($token)->toContain('.');

    $parts = explode('.', $token);
    expect($parts)->toHaveCount(2);
});

test('validateAccessToken returns payload for valid token', function () {
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->for($user)->online()->create();

    $controller = new DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');
    $token = $method->invoke($controller, $device);

    $payload = DeviceOAuthController::validateAccessToken($token);

    expect($payload)->not->toBeNull();
    expect($payload['sub'])->toBe($user->id);
    expect($payload['did'])->toBe($device->id);
    expect($payload['iat'])->toBeInt();
    expect($payload['exp'])->toBeInt();
    expect($payload['exp'])->toBeGreaterThan($payload['iat']);
    expect($payload['exp'] - $payload['iat'])->toBe(3600);
});

test('validateAccessToken returns null for tampered token', function () {
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->for($user)->online()->create();

    $controller = new DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'generateAccessToken');
    $token = $method->invoke($controller, $device);

    // Tamper with the signature
    $parts = explode('.', $token);
    $parts[1] = 'tampered_signature';
    $tamperedToken = implode('.', $parts);

    expect(DeviceOAuthController::validateAccessToken($tamperedToken))->toBeNull();
});

test('validateAccessToken returns null for invalid format', function () {
    expect(DeviceOAuthController::validateAccessToken('invalid-token'))->toBeNull();
    expect(DeviceOAuthController::validateAccessToken(''))->toBeNull();
    expect(DeviceOAuthController::validateAccessToken('a.b.c'))->toBeNull();
});

test('validateAccessToken returns null for expired token', function () {
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->for($user)->create();

    // Manually create an expired token
    $payload = [
        'sub' => $user->id,
        'did' => $device->id,
        'iat' => time() - 7200,
        'exp' => time() - 3600, // expired 1 hour ago
    ];

    $payloadJson = json_encode($payload);
    $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));
    $token = $payloadBase64.'.'.$signature;

    expect(DeviceOAuthController::validateAccessToken($token))->toBeNull();
});

test('findDeviceByRefreshToken finds correct device', function () {
    $user = User::factory()->create();
    $refreshToken = 'test-refresh-token-1234567890';

    $device = DeviceAuthorization::factory()->for($user)->create([
        'refresh_token_hash' => \Hash::make($refreshToken),
    ]);

    $controller = new DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'findDeviceByRefreshToken');

    $found = $method->invoke($controller, $refreshToken);

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($device->id);
});

test('findDeviceByRefreshToken returns null for invalid token', function () {
    User::factory()->create();
    DeviceAuthorization::factory()->create();

    $controller = new DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'findDeviceByRefreshToken');

    $found = $method->invoke($controller, 'wrong-refresh-token');

    expect($found)->toBeNull();
});

test('findDeviceByRefreshToken skips revoked devices', function () {
    $refreshToken = 'test-refresh-token-1234567890';

    DeviceAuthorization::factory()->revoked()->create([
        'refresh_token_hash' => \Hash::make($refreshToken),
    ]);

    $controller = new DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'findDeviceByRefreshToken');

    $found = $method->invoke($controller, $refreshToken);

    expect($found)->toBeNull();
});

test('handlePotentialTokenReuse revokes device on reuse', function () {
    $previousToken = 'previous-refresh-token-1234567890';

    $device = DeviceAuthorization::factory()->create([
        'previous_refresh_token_hash' => \Hash::make($previousToken),
    ]);

    $controller = new DeviceOAuthController;
    $method = new ReflectionMethod($controller, 'handlePotentialTokenReuse');

    \Illuminate\Support\Facades\Event::fake();

    $method->invoke($controller, $previousToken);

    $device->refresh();
    expect($device->isRevoked())->toBeTrue();
});
