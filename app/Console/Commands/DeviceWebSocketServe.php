<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\WebSocket\DeviceWebSocketHandler;
use App\Services\WebSocket\ReactPhpWebSocketConnection;
use GuzzleHttp\Psr7\Message;
use Illuminate\Console\Command;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class DeviceWebSocketServe extends Command
{
    protected $signature = 'device:websocket-serve
        {--host=0.0.0.0 : The host to listen on}
        {--port=8085 : The port to listen on}';

    protected $description = 'Start the device WebSocket server';

    public function handle(DeviceWebSocketHandler $handler): int
    {
        $host = $this->option('host');
        $port = $this->option('port');
        $negotiator = new ServerNegotiator;

        $server = new SocketServer("{$host}:{$port}");

        $server->on('connection', function (ConnectionInterface $stream) use ($handler, $negotiator): void {
            $headerBuffer = '';

            $stream->on('data', function ($data) use ($stream, $handler, $negotiator, &$headerBuffer): void {
                // If we haven't completed handshake yet, buffer headers
                if ($headerBuffer !== null) {
                    $headerBuffer .= $data;

                    if (! str_contains($headerBuffer, "\r\n\r\n")) {
                        return;
                    }

                    $request = Message::parseRequest($headerBuffer);
                    $headerBuffer = null;

                    // Check path
                    if ($request->getUri()->getPath() !== '/ws/device') {
                        $stream->write("HTTP/1.1 404 Not Found\r\n\r\n");
                        $stream->end();

                        return;
                    }

                    // Authenticate via Bearer token
                    $authHeader = $request->getHeaderLine('Authorization');
                    $token = str_starts_with($authHeader, 'Bearer ')
                        ? substr($authHeader, 7)
                        : null;

                    if (! $token) {
                        $stream->write("HTTP/1.1 401 Unauthorized\r\n\r\n");
                        $stream->end();

                        return;
                    }

                    $device = Device::findByToken($token);

                    if (! $device) {
                        $stream->write("HTTP/1.1 401 Unauthorized\r\n\r\n");
                        $stream->end();

                        return;
                    }

                    if ($device->token_expires_at && $device->token_expires_at->isPast()) {
                        $stream->write("HTTP/1.1 401 Unauthorized\r\n\r\n");
                        $stream->end();

                        return;
                    }

                    // Perform WebSocket handshake
                    $response = $negotiator->handshake($request);

                    if ($response->getStatusCode() !== 101) {
                        $stream->write(Message::toString($response));
                        $stream->end();

                        return;
                    }

                    $stream->write(Message::toString($response));

                    $connection = new ReactPhpWebSocketConnection($stream);
                    $stream->device = $device;
                    $stream->wsConnection = $connection;

                    // Set up message buffer for WebSocket framing
                    $messageBuffer = new MessageBuffer(
                        new CloseFrameChecker,
                        function ($message) use ($handler, $connection): void {
                            $handler->onMessage($connection, $message->getPayload());
                        },
                        function (Frame $frame) use ($stream, $handler, $connection): void {
                            if ($frame->getOpcode() === Frame::OP_CLOSE) {
                                $handler->onClose($connection);
                                $stream->end();
                            } elseif ($frame->getOpcode() === Frame::OP_PING) {
                                $pong = new Frame($frame->getPayload(), true, Frame::OP_PONG);
                                $stream->write($pong->getContents());
                            }
                        },
                    );

                    $stream->messageBuffer = $messageBuffer;

                    $handler->onOpen($connection, $device);

                    return;
                }

                // Post-handshake: feed data to message buffer
                if (isset($stream->messageBuffer)) {
                    $stream->messageBuffer->onData($data);
                }
            });

            $stream->on('close', function () use ($stream, $handler): void {
                if (isset($stream->wsConnection)) {
                    $handler->onClose($stream->wsConnection);
                }
            });

            $stream->on('error', function (\Throwable $e) use ($stream, $handler): void {
                if (isset($stream->wsConnection)) {
                    $handler->onError($stream->wsConnection, $e);
                }
            });
        });

        $this->info("Device WebSocket server started on ws://{$host}:{$port}/ws/device");

        Loop::run();

        return self::SUCCESS;
    }
}
