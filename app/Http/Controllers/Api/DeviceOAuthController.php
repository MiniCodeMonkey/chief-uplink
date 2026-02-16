<?php

namespace App\Http\Controllers\Api;

use App\Events\DeviceTokenRevoked;
use App\Http\Controllers\Controller;
use App\Models\CloudDeployment;
use App\Models\DeviceAuthorization;
use App\Models\OauthDeviceCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeviceOAuthController extends Controller
{
    /**
     * POST /oauth/device/code
     *
     * Generate a new device code for CLI authorization.
     * Called by chief CLI when running `chief login`.
     */
    public function requestCode(Request $request): JsonResponse
    {
        $request->validate([
            'device_name' => 'required|string|max:255',
        ]);

        $deviceCode = Str::uuid()->toString();
        $userCode = strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        $record = OauthDeviceCode::create([
            'device_code' => $deviceCode,
            'user_code' => $userCode,
            'device_name' => $request->input('device_name'),
            'expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'device_code' => $record->device_code,
            'user_code' => $record->user_code,
            'verification_uri' => url('/oauth/device'),
            'expires_in' => 900,
            'interval' => 5,
        ], 200);
    }

    /**
     * POST /oauth/device/token
     *
     * Poll for token after device code is generated.
     * Returns access token + refresh token on approval.
     */
    public function pollToken(Request $request): JsonResponse
    {
        $request->validate([
            'device_code' => 'required|string',
        ]);

        $record = OauthDeviceCode::where('device_code', $request->input('device_code'))->first();

        if (! $record) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'The device code is invalid.',
            ], 400);
        }

        if ($record->isExpired()) {
            $record->update(['status' => 'expired']);

            return response()->json([
                'error' => 'expired_token',
                'error_description' => 'The device code has expired. Please request a new one.',
            ], 400);
        }

        // Enforce minimum 5-second polling interval
        if ($record->last_polled_at && $record->last_polled_at->diffInSeconds(now()) < 5) {
            return response()->json([
                'error' => 'slow_down',
                'error_description' => 'Polling too fast. Please wait at least 5 seconds between requests.',
            ], 400);
        }

        $record->update(['last_polled_at' => now()]);

        if ($record->status === 'denied') {
            return response()->json([
                'error' => 'access_denied',
                'error_description' => 'The user denied the authorization request.',
            ], 400);
        }

        if ($record->status === 'pending') {
            return response()->json([
                'error' => 'authorization_pending',
                'error_description' => 'The authorization request is still pending.',
            ], 400);
        }

        // Status is 'approved' — issue tokens
        return $this->issueTokens($record->user, $record->device_name);
    }

    /**
     * POST /oauth/token
     *
     * Refresh an access token using a refresh token.
     * Rotates the refresh token (old one invalidated, new one issued).
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $request->validate([
            'grant_type' => 'required|string|in:refresh_token',
            'refresh_token' => 'required|string',
        ]);

        $refreshToken = $request->input('refresh_token');

        // Find the device authorization by checking the refresh token hash
        $device = $this->findDeviceByRefreshToken($refreshToken);

        if (! $device) {
            // If no matching device found, check if this is a previously-rotated token
            // (compromise detection) — revoke all tokens for devices that previously used this token
            $this->handlePotentialTokenReuse($refreshToken);

            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'The refresh token is invalid.',
            ], 400);
        }

        if ($device->isRevoked()) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'The refresh token has been revoked.',
            ], 400);
        }

        // Rotate the refresh token — store previous hash for compromise detection
        $newRefreshToken = Str::random(64);
        $device->update([
            'previous_refresh_token_hash' => $device->refresh_token_hash,
            'refresh_token_hash' => Hash::make($newRefreshToken),
            'last_ip' => $request->ip(),
        ]);

        $accessToken = $this->generateAccessToken($device);

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $newRefreshToken,
            'ws_url' => $this->buildWsUrl(),
        ]);
    }

    /**
     * POST /oauth/revoke
     *
     * Revoke a refresh token for a device.
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $refreshToken = $request->input('token');
        $device = $this->findDeviceByRefreshToken($refreshToken);

        if ($device) {
            $this->revokeDevice($device);
        }

        // Always return 200 per OAuth 2.0 spec (even if token not found)
        return response()->json([], 200);
    }

    /**
     * POST /oauth/device/exchange
     *
     * Exchange a one-time setup token (from VPS provisioning) for access + refresh tokens.
     * Used for automated VPS auth so the user doesn't need to manually run chief login.
     */
    public function exchangeSetupToken(Request $request): JsonResponse
    {
        $request->validate([
            'setup_token' => 'required|string',
        ]);

        $deployment = CloudDeployment::where('setup_token', $request->input('setup_token'))
            ->whereNotNull('setup_token')
            ->first();

        if (! $deployment) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'The setup token is invalid.',
            ], 400);
        }

        if ($deployment->setup_token_expires_at && $deployment->setup_token_expires_at->isPast()) {
            // Clear the expired token
            $deployment->update(['setup_token' => null, 'setup_token_expires_at' => null]);

            return response()->json([
                'error' => 'expired_token',
                'error_description' => 'The setup token has expired.',
            ], 400);
        }

        // Issue tokens for the deployment's user
        $response = $this->issueTokens($deployment->user, 'cloud-'.$deployment->provider.'-'.$deployment->id);

        // Clear the setup token (single-use)
        $deployment->update(['setup_token' => null, 'setup_token_expires_at' => null]);

        // Link the device authorization to the cloud deployment
        $responseData = json_decode($response->getContent(), true);
        if (isset($responseData['device_id'])) {
            $deployment->update(['device_authorization_id' => $responseData['device_id']]);
        }

        return $response;
    }

    /**
     * Issue access + refresh tokens for a user, creating a device authorization record.
     */
    private function issueTokens($user, string $deviceName): JsonResponse
    {
        $refreshToken = Str::random(64);

        $device = DeviceAuthorization::create([
            'user_id' => $user->id,
            'device_name' => $deviceName,
            'refresh_token_hash' => Hash::make($refreshToken),
        ]);

        $accessToken = $this->generateAccessToken($device);

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $refreshToken,
            'device_id' => $device->id,
            'ws_url' => $this->buildWsUrl(),
        ]);
    }

    /**
     * Build the WebSocket URL from Reverb configuration.
     *
     * Returns the full WS URL (e.g. wss://host/ws/server) or null
     * if REVERB_HOST is not configured (local dev without Reverb).
     */
    private function buildWsUrl(): ?string
    {
        $options = config('reverb.apps.apps.0.options', []);
        $host = $options['host'] ?? null;

        if (! $host) {
            return null;
        }

        $scheme = ($options['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws';
        $port = (int) ($options['port'] ?? ($scheme === 'wss' ? 443 : 80));
        $defaultPort = $scheme === 'wss' ? 443 : 80;

        $url = "{$scheme}://{$host}";

        if ($port !== $defaultPort) {
            $url .= ":{$port}";
        }

        return "{$url}/ws/server";
    }

    /**
     * Generate a signed access token (JWT-like) for a device.
     * The token contains the device ID and user ID, signed with the app key.
     * It is short-lived (1 hour) and not stored in the database.
     */
    private function generateAccessToken(DeviceAuthorization $device): string
    {
        $payload = [
            'sub' => $device->user_id,
            'did' => $device->id,
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $payloadJson = json_encode($payload);
        $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));

        return $payloadBase64.'.'.$signature;
    }

    /**
     * Validate and decode an access token.
     * Returns the payload array if valid, null otherwise.
     */
    public static function validateAccessToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadBase64, $signature] = $parts;

        $expectedSignature = hash_hmac('sha256', $payloadBase64, config('app.key'));
        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payloadJson = base64_decode(strtr($payloadBase64, '-_', '+/'));
        $payload = json_decode($payloadJson, true);

        if (! $payload || ! isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Find a device authorization by checking its refresh token against stored hashes.
     */
    private function findDeviceByRefreshToken(string $refreshToken): ?DeviceAuthorization
    {
        // We need to check each device's hash since bcrypt doesn't allow lookup
        $devices = DeviceAuthorization::whereNull('revoked_at')->get();

        foreach ($devices as $device) {
            if (Hash::check($refreshToken, $device->refresh_token_hash)) {
                return $device;
            }
        }

        return null;
    }

    /**
     * Handle potential token reuse (compromise detection).
     * If a previously-rotated refresh token is reused, revoke all tokens for that device.
     */
    private function handlePotentialTokenReuse(string $refreshToken): void
    {
        // Check all non-revoked devices to see if this matches a previous refresh token
        $devices = DeviceAuthorization::whereNull('revoked_at')
            ->whereNotNull('previous_refresh_token_hash')
            ->get();

        foreach ($devices as $device) {
            if (Hash::check($refreshToken, $device->previous_refresh_token_hash)) {
                // A previously-used token was reused — potential compromise
                // Revoke all tokens for this device
                $this->revokeDevice($device);

                return;
            }
        }
    }

    /**
     * Revoke a device authorization and broadcast the revocation event.
     */
    private function revokeDevice(DeviceAuthorization $device): void
    {
        $device->update([
            'revoked_at' => now(),
            'is_online' => false,
        ]);

        DeviceTokenRevoked::dispatch($device->id, $device->user_id);
    }
}
