<?php

namespace App\Services;

use App\Events\ChiefMessageReceived;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PrdSessionManager
{
    /**
     * Register a PRD session as active.
     */
    public function registerSession(string $sessionId, int $deviceId, int $userId, ?string $prdId = null): void
    {
        $now = time();

        $sessionData = [
            'device_id' => $deviceId,
            'user_id' => $userId,
            'prd_id' => $prdId ?? '',
            'created_at' => $now,
            'last_activity_at' => $now,
        ];

        Redis::hmset($this->sessionKey($sessionId), $sessionData);
        Redis::expire($this->sessionKey($sessionId), $this->sessionTimeout() + 300);

        // Track this session for the device
        Redis::sadd($this->deviceSessionsKey($deviceId), $sessionId);

        // Add to the expiring sessions sorted set for efficient scanning
        $expiresAt = $now + $this->sessionTimeout();
        Redis::zadd($this->expiringSessionsKey(), $expiresAt, $sessionId);

        Log::debug('PRD session registered', [
            'session_id' => $sessionId,
            'device_id' => $deviceId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Update the last activity timestamp for a session (extends timeout).
     */
    public function touchSession(string $sessionId): bool
    {
        $key = $this->sessionKey($sessionId);

        if (! Redis::exists($key)) {
            return false;
        }

        $now = time();
        Redis::hset($key, 'last_activity_at', (string) $now);
        Redis::expire($key, $this->sessionTimeout() + 300);

        // Update expiry time in sorted set
        $expiresAt = $now + $this->sessionTimeout();
        Redis::zadd($this->expiringSessionsKey(), $expiresAt, $sessionId);

        return true;
    }

    /**
     * Get session data, or null if the session doesn't exist.
     *
     * @return array{device_id: int, user_id: int, prd_id: string|null, created_at: int, last_activity_at: int}|null
     */
    public function getSession(string $sessionId): ?array
    {
        $data = Redis::hgetall($this->sessionKey($sessionId));

        if (empty($data)) {
            return null;
        }

        return [
            'device_id' => (int) $data['device_id'],
            'user_id' => (int) $data['user_id'],
            'prd_id' => $data['prd_id'] !== '' ? $data['prd_id'] : null,
            'created_at' => (int) $data['created_at'],
            'last_activity_at' => (int) $data['last_activity_at'],
        ];
    }

    /**
     * Get the time remaining in seconds for a session before it times out.
     */
    public function getTimeRemaining(string $sessionId): ?int
    {
        $session = $this->getSession($sessionId);
        if (! $session) {
            return null;
        }

        $expiresAt = $session['last_activity_at'] + $this->sessionTimeout();
        $remaining = $expiresAt - time();

        return max(0, $remaining);
    }

    /**
     * Close a session (remove from tracking).
     */
    public function closeSession(string $sessionId): void
    {
        $session = $this->getSession($sessionId);

        if ($session) {
            Redis::srem($this->deviceSessionsKey($session['device_id']), $sessionId);
        }

        Redis::del($this->sessionKey($sessionId));
        Redis::zrem($this->expiringSessionsKey(), $sessionId);

        Log::debug('PRD session closed', [
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Close all sessions for a device.
     */
    public function closeDeviceSessions(int $deviceId): int
    {
        $sessionsKey = $this->deviceSessionsKey($deviceId);
        $sessions = Redis::smembers($sessionsKey);
        $count = 0;

        foreach ($sessions as $sessionId) {
            Redis::del($this->sessionKey($sessionId));
            Redis::zrem($this->expiringSessionsKey(), $sessionId);
            $count++;
        }

        Redis::del($sessionsKey);

        return $count;
    }

    /**
     * Get all active session IDs for a device.
     *
     * @return string[]
     */
    public function getDeviceSessions(int $deviceId): array
    {
        return Redis::smembers($this->deviceSessionsKey($deviceId)) ?: [];
    }

    /**
     * Check all sessions and send timeout warnings / expire sessions as needed.
     *
     * @return array{warnings: int, expired: int}
     */
    public function checkTimeouts(): array
    {
        $warnings = 0;
        $expired = 0;
        $now = time();

        // Get sessions that are expiring soon or have already expired
        // Check sessions expiring within the warning threshold
        $warningThreshold = $now + $this->warningMinutes()[0] * 60;
        $sessionIds = Redis::zrangebyscore($this->expiringSessionsKey(), '-inf', (string) $warningThreshold);

        if (empty($sessionIds)) {
            return ['warnings' => 0, 'expired' => 0];
        }

        foreach ($sessionIds as $sessionId) {
            $session = $this->getSession($sessionId);
            if (! $session) {
                // Stale entry — clean up
                Redis::zrem($this->expiringSessionsKey(), $sessionId);

                continue;
            }

            $remaining = ($session['last_activity_at'] + $this->sessionTimeout()) - $now;

            if ($remaining <= 0) {
                // Session has expired
                $this->expireSession($sessionId, $session);
                $expired++;
            } else {
                // Check if we need to send a warning
                $minutesRemaining = (int) ceil($remaining / 60);
                $warningMinutes = $this->warningMinutes();

                foreach ($warningMinutes as $warnAt) {
                    if ($minutesRemaining <= $warnAt) {
                        $warningKey = $this->warningFlagKey($sessionId, $warnAt);
                        // Only send each warning level once
                        if (! Redis::exists($warningKey)) {
                            $this->sendTimeoutWarning($sessionId, $session, $minutesRemaining);
                            Redis::set($warningKey, '1');
                            Redis::expire($warningKey, $warnAt * 60 + 60);
                            $warnings++;
                        }
                        break;
                    }
                }
            }
        }

        return ['warnings' => $warnings, 'expired' => $expired];
    }

    /**
     * Send a timeout warning event to the browser.
     *
     * @param  array{device_id: int, user_id: int, prd_id: string|null, created_at: int, last_activity_at: int}  $session
     */
    protected function sendTimeoutWarning(string $sessionId, array $session, int $minutesRemaining): void
    {
        ChiefMessageReceived::dispatch(
            $session['device_id'],
            $session['user_id'],
            [
                'type' => 'session_timeout_warning',
                'session_id' => $sessionId,
                'minutes_remaining' => $minutesRemaining,
            ]
        );

        Log::debug('PRD session timeout warning sent', [
            'session_id' => $sessionId,
            'minutes_remaining' => $minutesRemaining,
        ]);
    }

    /**
     * Expire a session and notify the browser.
     *
     * @param  array{device_id: int, user_id: int, prd_id: string|null, created_at: int, last_activity_at: int}  $session
     */
    protected function expireSession(string $sessionId, array $session): void
    {
        ChiefMessageReceived::dispatch(
            $session['device_id'],
            $session['user_id'],
            [
                'type' => 'session_expired',
                'session_id' => $sessionId,
            ]
        );

        $this->closeSession($sessionId);

        Log::info('PRD session expired', [
            'session_id' => $sessionId,
            'device_id' => $session['device_id'],
        ]);
    }

    /**
     * Get the session timeout in seconds.
     */
    public function sessionTimeout(): int
    {
        return (int) config('websocket.prd_session_timeout', 1800);
    }

    /**
     * Get the warning thresholds in minutes (sorted descending).
     *
     * @return int[]
     */
    protected function warningMinutes(): array
    {
        return [10, 5, 1];
    }

    /**
     * Redis key for session data.
     */
    protected function sessionKey(string $sessionId): string
    {
        return "prd:session:{$sessionId}";
    }

    /**
     * Redis key for tracking sessions per device.
     */
    protected function deviceSessionsKey(int $deviceId): string
    {
        return "prd:device:{$deviceId}:sessions";
    }

    /**
     * Redis sorted set key for efficiently checking expiring sessions.
     */
    protected function expiringSessionsKey(): string
    {
        return 'prd:sessions:expiring';
    }

    /**
     * Redis key to flag that a specific warning level has been sent.
     */
    protected function warningFlagKey(string $sessionId, int $minutes): string
    {
        return "prd:warning:{$sessionId}:{$minutes}";
    }
}
