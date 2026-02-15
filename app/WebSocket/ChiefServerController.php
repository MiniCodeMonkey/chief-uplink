<?php

namespace App\WebSocket;

use App\Services\ServerConnectionManager;
use Illuminate\Support\Facades\Log;
use Laravel\Reverb\Servers\Reverb\Connection;
use Psr\Http\Message\RequestInterface;

class ChiefServerController
{
    public function __construct(
        protected ServerConnectionManager $connectionManager,
    ) {}

    /**
     * Handle a new WebSocket connection from a chief server.
     *
     * The chief server must send a "hello" message as its first message
     * containing the access token for authentication.
     */
    public function __invoke(RequestInterface $request, Connection $connection): void
    {
        $connectionId = $connection->id();

        $connection->withMaxMessageSize(65536);

        $connection->onMessage(function ($message) use ($connection, $connectionId) {
            $this->handleMessage($connection, $connectionId, (string) $message);
        });

        $connection->onClose(function () use ($connectionId) {
            $this->handleClose($connectionId);
        });

        $connection->openBuffer();

        Log::debug('Chief server WebSocket connection opened', [
            'connection_id' => $connectionId,
        ]);
    }

    /**
     * Handle an incoming message from a chief server.
     */
    protected function handleMessage(Connection $connection, int $connectionId, string $rawMessage): void
    {
        $message = json_decode($rawMessage, true);

        if (! is_array($message)) {
            Log::warning('Invalid JSON message from chief server', [
                'connection_id' => $connectionId,
            ]);

            return;
        }

        // If not yet authenticated, only accept "hello" messages
        if (! $this->connectionManager->isAuthenticated($connectionId)) {
            if (($message['type'] ?? null) !== 'hello') {
                Log::warning('Message received before hello from chief server', [
                    'connection_id' => $connectionId,
                    'type' => $message['type'] ?? 'unknown',
                ]);

                return;
            }

            $result = $this->connectionManager->handleHello($connectionId, $message);
            $connection->send(json_encode($result['response']));

            if (! $result['success']) {
                $connection->close();
            }

            return;
        }

        // Connection is authenticated — handle other message types
        $deviceId = $this->connectionManager->getDeviceId($connectionId);
        $type = $message['type'] ?? 'unknown';

        // Buffer the message for browser replay on reconnect
        $this->connectionManager->bufferMessage($deviceId, $message);

        // Message relay will be implemented in US-016
        Log::debug('Message from authenticated chief server', [
            'connection_id' => $connectionId,
            'device_id' => $deviceId,
            'type' => $type,
        ]);
    }

    /**
     * Handle a chief server connection being closed.
     */
    protected function handleClose(int $connectionId): void
    {
        $this->connectionManager->handleDisconnect($connectionId);
    }
}
