<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeviceCodeRequest;
use App\Models\Device;
use App\Models\DeviceCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceAuthController extends Controller
{
    public function request(DeviceCodeRequest $request): JsonResponse
    {
        $deviceCode = DeviceCode::create([
            'device_code' => Str::random(40),
            'user_code' => strtoupper(Str::random(8)),
            'device_name' => $request->validated('device_name'),
            'expires_at' => now()->addSeconds(900),
        ]);

        return response()->json([
            'device_code' => $deviceCode->device_code,
            'user_code' => $deviceCode->user_code,
            'verify_url' => url('/devices/verify'),
            'expires_in' => 900,
            'interval' => 5,
        ], 200);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_code' => ['required', 'string'],
        ]);

        $deviceCode = DeviceCode::where('device_code', $validated['device_code'])->first();

        if (! $deviceCode) {
            return response()->json(['error' => 'invalid_device_code'], 404);
        }

        if ($deviceCode->expires_at->isPast()) {
            return response()->json(['error' => 'expired_token'], 410);
        }

        if (! $deviceCode->approved_at) {
            return response()->json(['status' => 'pending'], 202);
        }

        $plainAccessToken = Str::random(64);
        $plainRefreshToken = Str::random(64);

        $device = Device::create([
            'team_id' => $deviceCode->team_id,
            'name' => $deviceCode->device_name,
            'os' => 'unknown',
            'arch' => 'unknown',
            'chief_version' => 'unknown',
            'access_token' => hash('sha256', $plainAccessToken),
            'refresh_token_hash' => hash('sha256', $plainRefreshToken),
            'token_expires_at' => now()->addDays(30),
        ]);

        $deviceCode->delete();

        return response()->json([
            'access_token' => $plainAccessToken,
            'refresh_token' => $plainRefreshToken,
            'expires_in' => 30 * 24 * 60 * 60,
            'device_id' => $device->id,
        ], 200);
    }
}
