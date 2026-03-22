<?php

namespace App\Services\WebSocket\Handlers;

use App\Contracts\WebSocketConnection;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

class CommandHandler
{
    /**
     * Handle a command message from a device.
     *
     * @param  array<string, mixed>  $message
     */
    public function handle(WebSocketConnection $connection, Device $device, array $message): void
    {
        $type = $message['type'] ?? '';

        Log::info('Command received from device', [
            'device_id' => $device->id,
            'command_type' => $type,
        ]);

        $connection->send([
            'type' => 'ack',
            'payload' => [
                'ref_id' => $message['id'] ?? null,
                'status' => 'received',
            ],
        ]);
    }
}
