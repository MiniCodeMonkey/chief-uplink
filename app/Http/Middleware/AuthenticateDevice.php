<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\DeviceOAuthController;
use App\Models\DeviceAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'missing_token',
                'error_description' => 'Access token is required.',
            ], 401);
        }

        $payload = DeviceOAuthController::validateAccessToken($token);

        if (! $payload) {
            // validateAccessToken returns null for both invalid signature and expired tokens.
            // We can distinguish by attempting to decode without signature verification.
            $error = $this->classifyTokenError($token);

            return response()->json([
                'error' => $error,
                'error_description' => match ($error) {
                    'expired_token' => 'The access token has expired.',
                    default => 'The access token signature is invalid.',
                },
            ], 401);
        }

        $device = DeviceAuthorization::find($payload['did']);

        if (! $device) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'The device was not found.',
            ], 401);
        }

        if ($device->isRevoked()) {
            return response()->json([
                'error' => 'revoked_device',
                'error_description' => 'The device has been revoked.',
            ], 401);
        }

        $request->attributes->set('device_id', $payload['did']);
        $request->attributes->set('user_id', $payload['sub']);
        $request->attributes->set('device_authorization', $device);

        return $next($request);
    }

    /**
     * Classify a token error as either expired or invalid signature.
     * Attempts to decode the payload without verifying the signature to check expiry.
     */
    private function classifyTokenError(string $token): string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return 'invalid_token';
        }

        $payloadJson = base64_decode(strtr($parts[0], '-_', '+/'));
        $payload = json_decode($payloadJson, true);

        if ($payload && isset($payload['exp']) && $payload['exp'] < time()) {
            return 'expired_token';
        }

        return 'invalid_token';
    }
}
