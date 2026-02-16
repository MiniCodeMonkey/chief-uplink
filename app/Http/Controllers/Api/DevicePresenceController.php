<?php

namespace App\Http\Controllers\Api;

use App\Events\DeviceConnected;
use App\Http\Controllers\Controller;
use App\Models\DeviceAuthorization;
use App\Services\WebSocketMessageBuffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DevicePresenceController extends Controller
{
    public function __construct(
        protected WebSocketMessageBuffer $messageBuffer,
    ) {}

    /**
     * POST /api/device/connect
     *
     * Register a device as online and return connection details.
     */
    public function connect(Request $request): JsonResponse
    {
        $request->validate([
            'chief_version' => 'nullable|string|max:50',
            'device_name' => 'nullable|string|max:255',
            'os' => 'nullable|string|max:50',
            'arch' => 'nullable|string|max:50',
            'protocol_version' => 'nullable|integer',
        ]);

        /** @var DeviceAuthorization $device */
        $device = $request->attributes->get('device_authorization');
        $deviceId = $request->attributes->get('device_id');
        $userId = $request->attributes->get('user_id');

        // Generate a new session ID for message buffering
        $sessionId = Str::uuid()->toString();

        // Mark device as reconnected in the message buffer
        try {
            $this->messageBuffer->markReconnected($deviceId);
        } catch (\Throwable $e) {
            Log::warning('Failed to mark device reconnected in buffer', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
        }

        // Update device status and metadata
        $updateData = [
            'is_online' => true,
            'last_connected_at' => now(),
            'session_id' => $sessionId,
        ];

        if ($request->filled('chief_version')) {
            $updateData['chief_version'] = $request->input('chief_version');
        }
        if ($request->filled('device_name')) {
            $updateData['device_name'] = $request->input('device_name');
        }
        if ($request->filled('os')) {
            $updateData['os'] = $request->input('os');
        }
        if ($request->filled('arch')) {
            $updateData['arch'] = $request->input('arch');
        }

        $device->update($updateData);

        // Broadcast device connected event to user's browser channel
        DeviceConnected::dispatch($deviceId, $userId);

        Log::info('Device connected via HTTP', [
            'device_id' => $deviceId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'chief_version' => $request->input('chief_version', 'unknown'),
        ]);

        // Build Reverb connection details from config
        $reverbApp = config('reverb.apps.apps.0.options', []);

        return response()->json([
            'type' => 'welcome',
            'protocol_version' => 1,
            'device_id' => $deviceId,
            'session_id' => $sessionId,
            'reverb' => [
                'key' => config('reverb.apps.apps.0.key'),
                'host' => $reverbApp['host'] ?? null,
                'port' => $reverbApp['port'] ?? 443,
                'scheme' => $reverbApp['scheme'] ?? 'https',
            ],
        ]);
    }
}
