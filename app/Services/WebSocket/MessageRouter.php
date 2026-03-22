<?php

namespace App\Services\WebSocket;

use App\Contracts\WebSocketConnection;
use App\Models\Device;
use App\Services\WebSocket\Handlers\CommandHandler;
use App\Services\WebSocket\Handlers\ControlHandler;
use App\Services\WebSocket\Handlers\StateHandler;

class MessageRouter
{
    /**
     * State message types that use slug format (from contract schemas).
     *
     * @var array<string>
     */
    private const STATE_TYPES = [
        'sync',
        'device-heartbeat',
        'prd-updated',
        'prd-created',
        'prd-deleted',
        'run-completed',
        'run-started',
        'run-stopped',
        'run-output',
        'run-progress',
        'projects-updated',
        'settings-updated',
        'log-output',
        'log-response',
        'file-response',
        'files-list',
        'diffs-response',
        'prd-chat-output',
        'project-clone-progress',
    ];

    /**
     * Control message types.
     *
     * @var array<string>
     */
    private const CONTROL_TYPES = [
        'ack',
        'error',
        'welcome',
    ];

    /**
     * Command message type prefixes (from contract schemas cmd/*).
     *
     * @var array<string>
     */
    private const CMD_PREFIXES = [
        'prd-create',
        'prd-update',
        'prd-delete',
        'prd-message',
        'run-start',
        'run-stop',
        'settings-get',
        'settings-update',
        'project-clone',
        'files-list',
        'file-get',
        'log-get',
        'diffs-get',
    ];

    public function __construct(
        private StateHandler $stateHandler,
        private ControlHandler $controlHandler,
        private CommandHandler $commandHandler,
    ) {}

    /**
     * Route an incoming message to the appropriate handler.
     *
     * @param  array<string, mixed>  $message
     */
    public function route(WebSocketConnection $connection, Device $device, array $message): void
    {
        $type = $message['type'] ?? '';

        if (str_starts_with($type, 'state.') || in_array($type, self::STATE_TYPES, true)) {
            $this->stateHandler->handle($connection, $device, $message);

            return;
        }

        if (in_array($type, self::CONTROL_TYPES, true)) {
            $this->controlHandler->handle($connection, $device, $message);

            return;
        }

        if (in_array($type, self::CMD_PREFIXES, true)) {
            $this->commandHandler->handle($connection, $device, $message);

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

    /**
     * Get the handler category for a given message type.
     */
    public function resolveCategory(string $type): string
    {
        if (str_starts_with($type, 'state.') || in_array($type, self::STATE_TYPES, true)) {
            return 'state';
        }

        if (in_array($type, self::CONTROL_TYPES, true)) {
            return 'control';
        }

        if (in_array($type, self::CMD_PREFIXES, true)) {
            return 'cmd';
        }

        return 'unknown';
    }
}
