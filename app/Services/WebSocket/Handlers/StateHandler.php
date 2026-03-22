<?php

namespace App\Services\WebSocket\Handlers;

use App\Contracts\WebSocketConnection;
use App\Models\Device;

class StateHandler
{
    /**
     * Handle a state.* message from a device.
     *
     * @param  array<string, mixed>  $message
     */
    public function handle(WebSocketConnection $connection, Device $device, array $message): void
    {
        $type = $message['type'] ?? '';

        match ($type) {
            'state.update' => $this->handleStateUpdate($connection, $device, $message),
            'state.sync' => $this->handleStateSync($connection, $device),
            default => $connection->send([
                'type' => 'error',
                'payload' => ['code' => 'unknown_state_type', 'message' => "Unknown state type: {$type}"],
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleStateUpdate(WebSocketConnection $connection, Device $device, array $message): void
    {
        $payload = $message['payload'] ?? [];

        if (isset($payload['chief_version'])) {
            $device->chief_version = $payload['chief_version'];
        }

        if (isset($payload['os'])) {
            $device->os = $payload['os'];
        }

        if (isset($payload['arch'])) {
            $device->arch = $payload['arch'];
        }

        $device->last_seen_at = now();
        $device->save();

        $connection->send([
            'type' => 'state.ack',
            'payload' => ['status' => 'ok'],
        ]);
    }

    private function handleStateSync(WebSocketConnection $connection, Device $device): void
    {
        $connection->send([
            'type' => 'state.current',
            'payload' => [
                'device_id' => $device->id,
                'name' => $device->name,
                'os' => $device->os,
                'arch' => $device->arch,
                'chief_version' => $device->chief_version,
            ],
        ]);
    }
}
