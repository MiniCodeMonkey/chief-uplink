<?php

namespace App\Services\WebSocket\Handlers;

use App\Contracts\WebSocketConnection;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class ControlHandler
{
    /**
     * Handle an ack or error message from a device.
     *
     * @param  array<string, mixed>  $message
     */
    public function handle(WebSocketConnection $connection, Device $device, array $message): void
    {
        $type = $message['type'] ?? '';

        match ($type) {
            'ack' => $this->handleAck($device, $message),
            'error' => $this->handleError($device, $message),
            default => $connection->send([
                'type' => 'error',
                'payload' => ['code' => 'unknown_control_type', 'message' => "Unknown control type: {$type}"],
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleAck(Device $device, array $message): void
    {
        $refId = $message['payload']['ref_id'] ?? null;

        if ($refId) {
            $device->pendingCommands()->where('id', $refId)->delete();
        }

        Log::debug('Device ack received', [
            'device_id' => $device->id,
            'ref_id' => $refId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleError(Device $device, array $message): void
    {
        Log::warning('Device error received', [
            'device_id' => $device->id,
            'error' => $message['payload'] ?? [],
        ]);
    }
}
