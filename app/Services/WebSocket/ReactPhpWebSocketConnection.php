<?php

namespace App\Services\WebSocket;

use App\Contracts\WebSocketConnection;
use Ratchet\RFC6455\Messaging\Frame;
use React\Socket\ConnectionInterface;

class ReactPhpWebSocketConnection implements WebSocketConnection
{
    private static int $nextId = 1;

    private int $id;

    public function __construct(private ConnectionInterface $stream)
    {
        $this->id = self::$nextId++;
    }

    public function send(array $data): void
    {
        $json = json_encode($data);
        $frame = new Frame($json, true, Frame::OP_TEXT);
        $this->stream->write($frame->getContents());
    }

    public function close(): void
    {
        $frame = new Frame('', true, Frame::OP_CLOSE);
        $this->stream->write($frame->getContents());
        $this->stream->end();
    }

    public function id(): int
    {
        return $this->id;
    }
}
