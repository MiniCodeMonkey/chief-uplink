<?php

namespace App\Services;

use App\Events\DeviceConnected;
use App\Events\DeviceDisconnected;
use App\Http\Controllers\Api\DeviceOAuthController;
use App\Models\DeviceAuthorization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ServerConnectionManager
{
    /**
     * Active connections indexed by connection ID.
     * Each entry contains: device_id, user_id, token_expires_at
     *
     * @var array<int, array{device_id: int, user_id: int, token_expires_at: int}>
     */
    protected array $connections = [];

    /**
     * Map of device_id to connection_id for quick lookups.
     *
     * @var array<int, int>
     */
    protected array $deviceToConnection = [];

    /**
     * Active session IDs indexed by device_id.
     *
     * @var array<int, string>
     */
    protected array $deviceSessions = [];

    /**
     * Process a "hello" message from a chief server connection.
     *
     * @return array{success: bool, response: array<string, mixed>}
     */
    public function handleHello(int $connectionId, array $message): array
    {
        // Validate required fields
        if (($message['type'] ?? null) !== 'hello') {
            return [
                'success' => false,
                'response' => [
                    'type' => 'auth_failed',
                    'code' => 'AUTH_FAILED',
                    'message' => 'Expected hello message.',
                ],
            ];
        }

        $accessToken = $message['access_token'] ?? null;
        if (! $accessToken) {
            return [
                'success' => false,
                'response' => [
                    'type' => 'auth_failed',
                    'code' => 'AUTH_FAILED',
                    'message' => 'Access token is required.',
                ],
            ];
        }

        // Validate the access token
        $payload = DeviceOAuthController::validateAccessToken($accessToken);
        if (! $payload) {
            return [
                'success' => false,
                'response' => [
                    'type' => 'auth_failed',
                    'code' => 'AUTH_FAILED',
                    'message' => 'Invalid or expired access token.',
                ],
            ];
        }

        $deviceId = $payload['did'];
        $userId = $payload['sub'];

        // Check device is not revoked
        $device = DeviceAuthorization::find($deviceId);
        if (! $device || $device->isRevoked()) {
            return [
                'success' => false,
                'response' => [
                    'type' => 'auth_failed',
                    'code' => 'AUTH_FAILED',
                    'message' => 'Device has been revoked.',
                ],
            ];
        }

        // If device was already connected, remove old connection
        if (isset($this->deviceToConnection[$deviceId])) {
            $oldConnectionId = $this->deviceToConnection[$deviceId];
            unset($this->connections[$oldConnectionId]);
        }

        // Register the connection
        $this->connections[$connectionId] = [
            'device_id' => $deviceId,
            'user_id' => $userId,
            'token_expires_at' => $payload['exp'],
        ];
        $this->deviceToConnection[$deviceId] = $connectionId;

        // Generate a session ID for message buffering
        $sessionId = Str::uuid()->toString();
        $this->deviceSessions[$deviceId] = $sessionId;

        // Mark device as reconnected in the message buffer
        try {
            app(WebSocketMessageBuffer::class)->markReconnected($deviceId);
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
        ];

        if (isset($message['chief_version'])) {
            $updateData['chief_version'] = $message['chief_version'];
        }
        if (isset($message['os'])) {
            $updateData['os'] = $message['os'];
        }
        if (isset($message['arch'])) {
            $updateData['arch'] = $message['arch'];
        }
        if (isset($message['device_name'])) {
            $updateData['device_name'] = $message['device_name'];
        }

        $device->update($updateData);

        // Broadcast device connected event to user's browser channel
        DeviceConnected::dispatch($deviceId, $userId);

        Log::info('Chief server authenticated', [
            'connection_id' => $connectionId,
            'device_id' => $deviceId,
            'user_id' => $userId,
            'chief_version' => $message['chief_version'] ?? 'unknown',
        ]);

        return [
            'success' => true,
            'response' => [
                'type' => 'welcome',
                'protocol_version' => 1,
                'connection_id' => $connectionId,
                'device_id' => $deviceId,
                'session_id' => $sessionId,
            ],
        ];
    }

    /**
     * Handle a connection being closed.
     */
    public function handleDisconnect(int $connectionId): void
    {
        $connectionData = $this->connections[$connectionId] ?? null;
        if (! $connectionData) {
            return;
        }

        $deviceId = $connectionData['device_id'];
        $userId = $connectionData['user_id'];

        // Clean up connection tracking
        unset($this->connections[$connectionId]);
        unset($this->deviceToConnection[$deviceId]);

        // Mark device as disconnected in the buffer (starts grace period)
        try {
            app(WebSocketMessageBuffer::class)->markDisconnected($deviceId);
        } catch (\Throwable $e) {
            Log::warning('Failed to mark device disconnected in buffer', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
        }

        // Update device status
        $device = DeviceAuthorization::find($deviceId);
        if ($device) {
            $device->update([
                'is_online' => false,
                'last_connected_at' => now(),
            ]);
        }

        // Broadcast device disconnected event to user's browser channel
        DeviceDisconnected::dispatch($deviceId, $userId);

        Log::info('Chief server disconnected', [
            'connection_id' => $connectionId,
            'device_id' => $deviceId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Check if a connection is authenticated.
     */
    public function isAuthenticated(int $connectionId): bool
    {
        return isset($this->connections[$connectionId]);
    }

    /**
     * Get the device ID for a connection.
     */
    public function getDeviceId(int $connectionId): ?int
    {
        return $this->connections[$connectionId]['device_id'] ?? null;
    }

    /**
     * Get the user ID for a connection.
     */
    public function getUserId(int $connectionId): ?int
    {
        return $this->connections[$connectionId]['user_id'] ?? null;
    }

    /**
     * Get the connection ID for a device.
     */
    public function getConnectionIdForDevice(int $deviceId): ?int
    {
        return $this->deviceToConnection[$deviceId] ?? null;
    }

    /**
     * Check connections for expiring tokens and return connection IDs that need refresh.
     *
     * @return array<int, int> Connection IDs with tokens expiring within the threshold
     */
    public function getConnectionsNeedingRefresh(int $thresholdSeconds = 300): array
    {
        $needsRefresh = [];
        $now = time();

        foreach ($this->connections as $connectionId => $data) {
            $timeRemaining = $data['token_expires_at'] - $now;
            if ($timeRemaining > 0 && $timeRemaining <= $thresholdSeconds) {
                $needsRefresh[] = $connectionId;
            }
        }

        return $needsRefresh;
    }

    /**
     * Get all active connections.
     *
     * @return array<int, array{device_id: int, user_id: int, token_expires_at: int}>
     */
    public function getActiveConnections(): array
    {
        return $this->connections;
    }

    /**
     * Get the current session ID for a device.
     */
    public function getSessionId(int $deviceId): ?string
    {
        return $this->deviceSessions[$deviceId] ?? null;
    }

    /**
     * Buffer a message from a chief server for browser replay.
     */
    public function bufferMessage(int $deviceId, array $message): bool
    {
        $sessionId = $this->deviceSessions[$deviceId] ?? null;
        if (! $sessionId) {
            return false;
        }

        try {
            return app(WebSocketMessageBuffer::class)->buffer($deviceId, $sessionId, $message);
        } catch (\Throwable $e) {
            Log::warning('Failed to buffer WebSocket message', [
                'device_id' => $deviceId,
                'type' => $message['type'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove a connection by device ID (e.g., when device is deauthorized).
     */
    public function disconnectDevice(int $deviceId): ?int
    {
        $connectionId = $this->deviceToConnection[$deviceId] ?? null;
        if ($connectionId !== null) {
            unset($this->connections[$connectionId]);
            unset($this->deviceToConnection[$deviceId]);
        }

        // Clean up session tracking (don't flush buffer — let grace period handle it)
        unset($this->deviceSessions[$deviceId]);

        return $connectionId;
    }
}
