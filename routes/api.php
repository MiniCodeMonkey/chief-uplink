<?php

use App\Http\Controllers\Api\DeviceOAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Device OAuth API Routes
|--------------------------------------------------------------------------
|
| These routes handle the device OAuth flow for chief CLI instances.
| All endpoints are public (no session/cookie auth) and use JSON.
|
*/

Route::middleware('throttle:general-api')->group(function () {
    // Device code request — CLI calls this to start the device flow
    Route::post('/oauth/device/code', [DeviceOAuthController::class, 'requestCode'])
        ->middleware('throttle:device-code')
        ->name('oauth.device.code');

    // Token polling — CLI polls this to check if device code was approved
    Route::post('/oauth/device/token', [DeviceOAuthController::class, 'pollToken'])
        ->name('oauth.device.token');

    // Token refresh — CLI uses this to get a new access token
    Route::post('/oauth/token', [DeviceOAuthController::class, 'refreshToken'])
        ->middleware('throttle:token-refresh')
        ->name('oauth.token');

    // Token revocation — CLI uses this to revoke its tokens
    Route::post('/oauth/revoke', [DeviceOAuthController::class, 'revokeToken'])
        ->name('oauth.revoke');

    // Setup token exchange — VPS uses this to auto-authenticate
    Route::post('/oauth/device/exchange', [DeviceOAuthController::class, 'exchangeSetupToken'])
        ->name('oauth.device.exchange');
});

/*
|--------------------------------------------------------------------------
| Authenticated Device API Routes
|--------------------------------------------------------------------------
|
| Routes that require a valid device access token (used by chief servers).
| The device.auth middleware validates the HMAC access token and checks
| that the device has not been revoked.
|
*/

Route::middleware(['device.auth', 'throttle:general-api'])->group(function () {
    Route::get('/device/status', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'authenticated' => true,
            'device_id' => $request->input('authenticated_device_id'),
            'user_id' => $request->input('authenticated_user_id'),
        ]);
    })->name('device.status');
});

/*
|--------------------------------------------------------------------------
| Device API Routes (new HTTP-based transport)
|--------------------------------------------------------------------------
|
| Routes for the HTTP+Pusher transport replacing the custom WebSocket.
| The device.api middleware authenticates via HMAC access tokens and
| sets device_id, user_id, and device_authorization on request attributes.
|
*/

Route::prefix('device')->middleware(['device.api'])->group(function () {
    Route::post('/connect', [\App\Http\Controllers\Api\DevicePresenceController::class, 'connect'])
        ->name('device.connect');
});
