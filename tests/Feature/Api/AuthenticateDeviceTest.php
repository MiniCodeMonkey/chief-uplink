<?php

use App\Models\DeviceAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Register a test route behind the device.api middleware
    Route::middleware('device.api')->get('/api/test/device-auth', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'device_id' => $request->attributes->get('device_id'),
            'user_id' => $request->attributes->get('user_id'),
            'device_name' => $request->attributes->get('device_authorization')->device_name,
        ]);
    });

    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->create([
        'device_name' => 'test-device',
    ]);
});

function generateAccessToken(DeviceAuthorization $device, int $expiresIn = 3600): string
{
    $payload = [
        'sub' => $device->user_id,
        'did' => $device->id,
        'iat' => time(),
        'exp' => time() + $expiresIn,
    ];

    $payloadJson = json_encode($payload);
    $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));

    return $payloadBase64.'.'.$signature;
}

/*
|--------------------------------------------------------------------------
| Successful authentication
|--------------------------------------------------------------------------
*/

test('successful authentication sets device attributes on request', function () {
    $token = generateAccessToken($this->device);

    $response = $this->getJson('/api/test/device-auth', [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJson([
            'device_id' => $this->device->id,
            'user_id' => $this->user->id,
            'device_name' => 'test-device',
        ]);
});

/*
|--------------------------------------------------------------------------
| Missing token
|--------------------------------------------------------------------------
*/

test('missing token returns 401 with missing_token error', function () {
    $response = $this->getJson('/api/test/device-auth');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'missing_token',
            'error_description' => 'Access token is required.',
        ]);
});

/*
|--------------------------------------------------------------------------
| Invalid signature
|--------------------------------------------------------------------------
*/

test('invalid signature returns 401 with invalid_token error', function () {
    $token = generateAccessToken($this->device);
    $parts = explode('.', $token);
    $parts[1] = 'tampered_signature';
    $tampered = implode('.', $parts);

    $response = $this->getJson('/api/test/device-auth', [
        'Authorization' => 'Bearer '.$tampered,
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'invalid_token',
        ]);
});

test('malformed token returns 401 with invalid_token error', function () {
    $response = $this->getJson('/api/test/device-auth', [
        'Authorization' => 'Bearer completely-invalid-token',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'invalid_token',
        ]);
});

/*
|--------------------------------------------------------------------------
| Expired token
|--------------------------------------------------------------------------
*/

test('expired token returns 401 with expired_token error', function () {
    // Create a token that's already expired
    $payload = [
        'sub' => $this->device->user_id,
        'did' => $this->device->id,
        'iat' => time() - 7200,
        'exp' => time() - 3600,
    ];
    $payloadJson = json_encode($payload);
    $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));
    $token = $payloadBase64.'.'.$signature;

    $response = $this->getJson('/api/test/device-auth', [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'expired_token',
            'error_description' => 'The access token has expired.',
        ]);
});

/*
|--------------------------------------------------------------------------
| Revoked device
|--------------------------------------------------------------------------
*/

test('revoked device returns 401 with revoked_device error', function () {
    $this->device->update(['revoked_at' => now()]);
    $token = generateAccessToken($this->device);

    $response = $this->getJson('/api/test/device-auth', [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'revoked_device',
            'error_description' => 'The device has been revoked.',
        ]);
});

/*
|--------------------------------------------------------------------------
| Non-existent device
|--------------------------------------------------------------------------
*/

test('token for non-existent device returns 401', function () {
    // Create a token with a device ID that doesn't exist
    $payload = [
        'sub' => $this->user->id,
        'did' => 99999,
        'iat' => time(),
        'exp' => time() + 3600,
    ];
    $payloadJson = json_encode($payload);
    $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));
    $token = $payloadBase64.'.'.$signature;

    $response = $this->getJson('/api/test/device-auth', [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'invalid_token',
            'error_description' => 'The device was not found.',
        ]);
});
