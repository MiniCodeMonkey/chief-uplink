<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ServerConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CommandRelayController extends Controller
{
    /**
     * Valid command types that can be sent from browser to chief.
     */
    public const VALID_COMMANDS = [
        'start_run',
        'pause_run',
        'resume_run',
        'stop_run',
        'new_prd',
        'prd_message',
        'close_prd_session',
        'clone_repo',
        'create_project',
        'get_logs',
        'get_diffs',
        'get_settings',
        'update_settings',
        'get_prds',
        'refine_prd',
    ];

    public function __construct(
        protected ServerConnectionManager $connectionManager,
    ) {}

    /**
     * Relay a command from the browser to a chief server via WebSocket.
     */
    public function send(Request $request, int $deviceId): JsonResponse
    {
        $request->validate([
            'type' => 'required|string',
            'payload' => 'nullable|array',
        ]);

        $type = $request->input('type');
        $payload = $request->input('payload', []);

        // Validate command type
        if (! in_array($type, self::VALID_COMMANDS, true)) {
            return response()->json([
                'error' => 'invalid_command',
                'message' => "Unknown command type: {$type}",
            ], 422);
        }

        // Verify the user owns this device and it's not revoked
        $device = $request->user()
            ->deviceAuthorizations()
            ->where('id', $deviceId)
            ->whereNull('revoked_at')
            ->first();

        if (! $device) {
            return response()->json([
                'error' => 'device_not_found',
                'message' => 'Device not found or not authorized.',
            ], 403);
        }

        // Check if the device is online
        if (! $this->connectionManager->isDeviceOnline($deviceId)) {
            return response()->json([
                'error' => 'server_offline',
                'message' => 'Server offline',
            ], 503);
        }

        // Build the message to send to chief
        $message = [
            'type' => $type,
            'payload' => $payload,
        ];

        // Send the message via WebSocket
        $sent = $this->connectionManager->sendToDevice($deviceId, $message);

        if (! $sent) {
            return response()->json([
                'error' => 'send_failed',
                'message' => 'Failed to send command to server.',
            ], 502);
        }

        Log::debug('Command relayed from browser to chief', [
            'user_id' => $request->user()->id,
            'device_id' => $deviceId,
            'type' => $type,
        ]);

        return response()->json([
            'status' => 'sent',
            'type' => $type,
            'device_id' => $deviceId,
        ]);
    }
}
