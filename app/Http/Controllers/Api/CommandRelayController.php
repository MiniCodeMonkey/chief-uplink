<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PrdSessionManager;
use App\Services\ServerConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

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
        protected PrdSessionManager $sessionManager,
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

        // Clone/create project commands have a stricter rate limit: 10 per user per hour
        if (in_array($type, ['clone_repo', 'create_project'], true)) {
            $key = 'clone-create-project:'.$request->user()->id;
            if (RateLimiter::tooManyAttempts($key, 10)) {
                $retryAfter = RateLimiter::availableIn($key);

                return response()->json([
                    'error' => 'rate_limited',
                    'message' => 'Too many clone/create requests. Please try again later.',
                    'retry_after' => $retryAfter,
                ], 429)->withHeaders([
                    'Retry-After' => $retryAfter,
                    'X-RateLimit-Limit' => 10,
                    'X-RateLimit-Remaining' => 0,
                ]);
            }
            RateLimiter::hit($key, 3600);
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

        // Track PRD session activity
        $this->trackPrdSession($type, $payload, $deviceId, $request->user()->id);

        Log::debug('Command relayed from browser to chief', [
            'user_id' => $request->user()->id,
            'device_id' => $deviceId,
            'type' => $type,
        ]);

        $response = [
            'status' => 'sent',
            'type' => $type,
            'device_id' => $deviceId,
        ];

        // Include time remaining for PRD session commands
        $sessionId = $payload['session_id'] ?? null;
        if ($sessionId && in_array($type, ['new_prd', 'refine_prd', 'prd_message'], true)) {
            $response['session_timeout_remaining'] = $this->sessionManager->getTimeRemaining($sessionId);
        }

        return response()->json($response);
    }

    /**
     * Track PRD session lifecycle events.
     */
    protected function trackPrdSession(string $type, array $payload, int $deviceId, int $userId): void
    {
        $sessionId = $payload['session_id'] ?? null;
        if (! $sessionId) {
            return;
        }

        match ($type) {
            'new_prd' => $this->sessionManager->registerSession(
                $sessionId, $deviceId, $userId
            ),
            'refine_prd' => $this->sessionManager->registerSession(
                $sessionId, $deviceId, $userId, $payload['prd_id'] ?? null
            ),
            'prd_message' => $this->sessionManager->touchSession($sessionId),
            'close_prd_session' => $this->sessionManager->closeSession($sessionId),
            default => null,
        };
    }
}
