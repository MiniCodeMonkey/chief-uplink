<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceHeartbeatController extends Controller
{
    /**
     * POST /api/device/heartbeat
     *
     * Update the device's heartbeat timestamp to indicate it's still alive.
     */
    public function beat(Request $request): JsonResponse
    {
        /** @var DeviceAuthorization $device */
        $device = $request->attributes->get('device_authorization');

        $device->update([
            'last_heartbeat_at' => now(),
        ]);

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
