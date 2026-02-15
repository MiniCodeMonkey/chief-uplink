<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebSocketMessageBuffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageBufferController extends Controller
{
    public function __construct(
        protected WebSocketMessageBuffer $buffer,
    ) {}

    /**
     * Replay buffered messages for a device.
     *
     * Called by the browser after reconnection to retrieve messages missed
     * during a brief disconnection.
     */
    public function replay(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|integer',
            'session_id' => 'nullable|string|max:255',
        ]);

        $deviceId = (int) $request->input('device_id');
        $sessionId = $request->input('session_id');

        // Verify the user owns this device
        $owns = $request->user()
            ->deviceAuthorizations()
            ->where('id', $deviceId)
            ->whereNull('revoked_at')
            ->exists();

        if (! $owns) {
            return response()->json([
                'error' => 'Device not found or not authorized.',
            ], 403);
        }

        if ($sessionId) {
            $messages = $this->buffer->replay($deviceId, $sessionId);

            return response()->json([
                'device_id' => $deviceId,
                'session_id' => $sessionId,
                'messages' => $messages,
            ]);
        }

        // Replay all sessions for the device
        $allMessages = $this->buffer->replayAll($deviceId);

        return response()->json([
            'device_id' => $deviceId,
            'sessions' => $allMessages,
        ]);
    }
}
