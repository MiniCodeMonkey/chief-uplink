<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceBroadcastAuthController extends Controller
{
    /**
     * POST /api/device/broadcasting/auth
     *
     * Authenticate a device for a private Pusher channel subscription.
     * Generates the Pusher auth signature using the Reverb app secret.
     */
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'socket_id' => 'required|string',
            'channel_name' => 'required|string',
        ]);

        $deviceId = $request->attributes->get('device_id');
        $socketId = $request->input('socket_id');
        $channelName = $request->input('channel_name');

        // Validate channel format: must be private-chief-server.{deviceId}
        $expectedChannel = "private-chief-server.{$deviceId}";

        if ($channelName !== $expectedChannel) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You are not authorized to subscribe to this channel.',
            ], 403);
        }

        // Generate Pusher auth signature: HMAC-SHA256("{socket_id}:{channel_name}", app_secret)
        $appKey = config('reverb.apps.apps.0.key');
        $appSecret = config('reverb.apps.apps.0.secret');

        $stringToSign = "{$socketId}:{$channelName}";
        $signature = hash_hmac('sha256', $stringToSign, $appSecret);

        return response()->json([
            'auth' => "{$appKey}:{$signature}",
        ]);
    }
}
