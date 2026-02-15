<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\DeviceOAuthController;
use App\Models\DeviceAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateDeviceAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Access token is required.',
            ], 401);
        }

        $payload = DeviceOAuthController::validateAccessToken($token);

        if (! $payload) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'The access token is invalid or expired.',
            ], 401);
        }

        // Check that the device is still authorized (not revoked)
        $device = DeviceAuthorization::find($payload['did']);

        if (! $device || $device->isRevoked()) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'The device has been revoked.',
            ], 401);
        }

        // Attach the device and user to the request for downstream use
        $request->merge([
            'device_authorization' => $device,
            'authenticated_user_id' => $payload['sub'],
            'authenticated_device_id' => $payload['did'],
        ]);

        return $next($request);
    }
}
