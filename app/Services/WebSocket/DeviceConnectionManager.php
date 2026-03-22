<?php

namespace App\Services\WebSocket;

use App\Contracts\WebSocketConnection;

class DeviceConnectionManager
{
    /** @var array<int, WebSocketConnection> */
    private array $connections = [];

    /** @var array<int, int> Maps connection ID to device ID */
    private array $deviceMap = [];

    /**
     * Track a new WebSocket connection for a device.
     */
    public function add(WebSocketConnection $connection, int $deviceId): void
    {
        $this->connections[$connection->id()] = $connection;
        $this->deviceMap[$connection->id()] = $deviceId;
    }

    /**
     * Remove a WebSocket connection and return its device ID.
     */
    public function remove(WebSocketConnection $connection): ?int
    {
        $deviceId = $this->deviceMap[$connection->id()] ?? null;

        unset($this->connections[$connection->id()], $this->deviceMap[$connection->id()]);

        return $deviceId;
    }

    /**
     * Get the connection for a device.
     */
    public function getConnectionForDevice(int $deviceId): ?WebSocketConnection
    {
        $connectionId = array_search($deviceId, $this->deviceMap);

        if ($connectionId === false) {
            return null;
        }

        return $this->connections[$connectionId] ?? null;
    }

    /**
     * Get the device ID for a connection.
     */
    public function getDeviceId(WebSocketConnection $connection): ?int
    {
        return $this->deviceMap[$connection->id()] ?? null;
    }

    /**
     * Get the count of active connections.
     */
    public function count(): int
    {
        return count($this->connections);
    }

    /**
     * Check if a device has an active connection.
     */
    public function isConnected(int $deviceId): bool
    {
        return in_array($deviceId, $this->deviceMap);
    }
}
