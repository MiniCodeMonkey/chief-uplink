<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WebSocketMessageBuffer
{
    /**
     * Bufferable message types that should be stored for replay.
     */
    public const BUFFERABLE_TYPES = [
        'claude_output',
        'run_progress',
        'run_complete',
        'run_paused',
        'clone_progress',
        'session_timeout_warning',
        'error',
        'quota_exhausted',
    ];

    /**
     * Non-bufferable message types that should be re-requested on reconnect.
     */
    public const NON_BUFFERABLE_TYPES = [
        'project_state',
        'project_list',
    ];

    /**
     * Get the Redis key for a device session buffer.
     */
    protected function bufferKey(int $deviceId, string $sessionId): string
    {
        return "ws:buffer:{$deviceId}:{$sessionId}";
    }

    /**
     * Get the Redis key tracking active sessions for a device.
     */
    protected function deviceSessionsKey(int $deviceId): string
    {
        return "ws:sessions:{$deviceId}";
    }

    /**
     * Get the Redis key for the disconnect timestamp of a device.
     */
    protected function disconnectTimestampKey(int $deviceId): string
    {
        return "ws:disconnect:{$deviceId}";
    }

    /**
     * Get the Redis key for tracking all disconnected devices.
     */
    protected function disconnectedDevicesKey(): string
    {
        return 'ws:disconnected_devices';
    }

    /**
     * Get the maximum buffer size in bytes.
     */
    protected function maxBufferSize(): int
    {
        return (int) config('websocket.buffer_max_size', 5 * 1024 * 1024);
    }

    /**
     * Get the grace period in seconds after device disconnect before flushing.
     */
    protected function gracePeriod(): int
    {
        return (int) config('websocket.buffer_grace_period', 300);
    }

    /**
     * Buffer a message for a device session.
     *
     * Returns true if the message was buffered, false if it was skipped (non-bufferable type).
     */
    public function buffer(int $deviceId, string $sessionId, array $message): bool
    {
        $type = $message['type'] ?? null;

        if (! $type || ! in_array($type, self::BUFFERABLE_TYPES, true)) {
            return false;
        }

        $key = $this->bufferKey($deviceId, $sessionId);
        $sessionsKey = $this->deviceSessionsKey($deviceId);

        $entry = json_encode([
            'message' => $message,
            'timestamp' => microtime(true),
        ]);

        if ($entry === false) {
            Log::warning('Failed to JSON encode WebSocket message for buffering', [
                'device_id' => $deviceId,
                'session_id' => $sessionId,
                'type' => $type,
            ]);

            return false;
        }

        // Push the message to the buffer list
        Redis::rpush($key, $entry);

        // Track this session for the device
        Redis::sadd($sessionsKey, $sessionId);

        // Enforce size cap by evicting oldest messages
        $this->enforceBufferCap($key);

        return true;
    }

    /**
     * Replay buffered messages for a device session in order.
     *
     * @return array<int, array{message: array, timestamp: float}>
     */
    public function replay(int $deviceId, string $sessionId): array
    {
        $key = $this->bufferKey($deviceId, $sessionId);

        $entries = Redis::lrange($key, 0, -1);

        if (empty($entries)) {
            return [];
        }

        $messages = [];
        foreach ($entries as $entry) {
            $decoded = json_decode($entry, true);
            if (is_array($decoded)) {
                $messages[] = $decoded;
            }
        }

        return $messages;
    }

    /**
     * Get all buffered messages for a device across all sessions.
     *
     * @return array<string, array<int, array{message: array, timestamp: float}>>
     */
    public function replayAll(int $deviceId): array
    {
        $sessionsKey = $this->deviceSessionsKey($deviceId);
        $sessions = Redis::smembers($sessionsKey);

        if (empty($sessions)) {
            return [];
        }

        $result = [];
        foreach ($sessions as $sessionId) {
            $messages = $this->replay($deviceId, $sessionId);
            if (! empty($messages)) {
                $result[$sessionId] = $messages;
            }
        }

        return $result;
    }

    /**
     * Flush buffer for a specific device session.
     */
    public function flushSession(int $deviceId, string $sessionId): void
    {
        $key = $this->bufferKey($deviceId, $sessionId);
        $sessionsKey = $this->deviceSessionsKey($deviceId);

        Redis::del($key);
        Redis::srem($sessionsKey, $sessionId);
    }

    /**
     * Flush all buffers for a device.
     */
    public function flushDevice(int $deviceId): void
    {
        $sessionsKey = $this->deviceSessionsKey($deviceId);
        $sessions = Redis::smembers($sessionsKey);

        if (! empty($sessions)) {
            $keys = array_map(
                fn ($sessionId) => $this->bufferKey($deviceId, $sessionId),
                $sessions
            );
            Redis::del(...$keys);
        }

        Redis::del($sessionsKey);
        Redis::del($this->disconnectTimestampKey($deviceId));
        Redis::zrem($this->disconnectedDevicesKey(), (string) $deviceId);
    }

    /**
     * Mark a device as disconnected (starts grace period timer).
     */
    public function markDisconnected(int $deviceId): void
    {
        $timestamp = time();

        // Store individual disconnect timestamp
        $key = $this->disconnectTimestampKey($deviceId);
        Redis::set($key, (string) $timestamp);
        Redis::expire($key, $this->gracePeriod() + 60);

        // Add to the disconnected devices sorted set for efficient cleanup scanning
        Redis::zadd($this->disconnectedDevicesKey(), $timestamp, (string) $deviceId);
    }

    /**
     * Mark a device as reconnected (clears disconnect timestamp).
     */
    public function markReconnected(int $deviceId): void
    {
        Redis::del($this->disconnectTimestampKey($deviceId));
        Redis::zrem($this->disconnectedDevicesKey(), (string) $deviceId);
    }

    /**
     * Get the disconnect timestamp for a device, if set.
     */
    public function getDisconnectTimestamp(int $deviceId): ?int
    {
        $timestamp = Redis::get($this->disconnectTimestampKey($deviceId));

        return $timestamp !== null ? (int) $timestamp : null;
    }

    /**
     * Check if a message type is bufferable.
     */
    public function isBufferable(string $type): bool
    {
        return in_array($type, self::BUFFERABLE_TYPES, true);
    }

    /**
     * Get the current buffer size in bytes for a device session.
     */
    public function getBufferSize(int $deviceId, string $sessionId): int
    {
        $key = $this->bufferKey($deviceId, $sessionId);

        $entries = Redis::lrange($key, 0, -1);

        if (empty($entries)) {
            return 0;
        }

        $size = 0;
        foreach ($entries as $entry) {
            $size += strlen($entry);
        }

        return $size;
    }

    /**
     * Get the number of messages in a device session buffer.
     */
    public function getMessageCount(int $deviceId, string $sessionId): int
    {
        $key = $this->bufferKey($deviceId, $sessionId);

        return (int) Redis::llen($key);
    }

    /**
     * Clean up stale buffers that have exceeded the grace period.
     *
     * @return int Number of devices cleaned up
     */
    public function cleanupStaleBuffers(): int
    {
        $cleaned = 0;
        $gracePeriod = $this->gracePeriod();
        $cutoff = time() - $gracePeriod;

        // Get all devices disconnected before the cutoff time using sorted set range
        $staleDeviceIds = Redis::zrangebyscore($this->disconnectedDevicesKey(), '-inf', (string) $cutoff);

        if (empty($staleDeviceIds)) {
            return 0;
        }

        foreach ($staleDeviceIds as $deviceIdStr) {
            $deviceId = (int) $deviceIdStr;

            if ($deviceId > 0) {
                $this->flushDevice($deviceId);
                $cleaned++;

                Log::info('Flushed stale WebSocket buffer', [
                    'device_id' => $deviceId,
                ]);
            }
        }

        return $cleaned;
    }

    /**
     * Enforce the buffer size cap by removing oldest messages.
     */
    protected function enforceBufferCap(string $key): void
    {
        $maxSize = $this->maxBufferSize();

        // Get current buffer size
        $entries = Redis::lrange($key, 0, -1);

        if (empty($entries)) {
            return;
        }

        $totalSize = 0;
        foreach ($entries as $entry) {
            $totalSize += strlen($entry);
        }

        // If under the cap, nothing to do
        if ($totalSize <= $maxSize) {
            return;
        }

        // Remove oldest entries until under cap
        while ($totalSize > $maxSize) {
            $removed = Redis::lpop($key);
            if ($removed === null) {
                break;
            }
            $totalSize -= strlen($removed);
        }

        Log::debug('WebSocket buffer cap enforced', [
            'key' => $key,
            'max_size' => $maxSize,
            'new_size' => $totalSize,
        ]);
    }
}
