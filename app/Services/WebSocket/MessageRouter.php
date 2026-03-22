<?php

namespace App\Services\WebSocket;

use App\Contracts\WebSocketConnection;
use App\Models\Device;
use App\Services\WebSocket\Handlers\ControlHandler;
use App\Services\WebSocket\Handlers\StateHandler;

class MessageRouter
{
    public function __construct(
        private StateHandler $stateHandler,
        private ControlHandler $controlHandler,
    ) {}

    /**
     * Route an incoming message to the appropriate handler.
     *
     * @param  array<string, mixed>  $message
     */
    public function route(WebSocketConnection $connection, Device $device, array $message): void
    {
        $type = $message['type'] ?? '';

        if (str_starts_with($type, 'state.')) {
            $this->stateHandler->handle($connection, $device, $message);

            return;
        }

        if ($type === 'ack' || $type === 'error') {
            $this->controlHandler->handle($connection, $device, $message);

            return;
        }

        $connection->send([
            'type' => 'error',
            'payload' => [
                'code' => 'unknown_message_type',
                'message' => "Unknown message type: {$type}",
            ],
        ]);
    }
}
