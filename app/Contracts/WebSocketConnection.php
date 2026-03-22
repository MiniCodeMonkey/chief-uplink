<?php

namespace App\Contracts;

interface WebSocketConnection
{
    /**
     * Send a JSON-encodable message to the client.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(array $data): void;

    /**
     * Close the connection.
     */
    public function close(): void;

    /**
     * Get the unique identifier for this connection.
     */
    public function id(): int;
}
