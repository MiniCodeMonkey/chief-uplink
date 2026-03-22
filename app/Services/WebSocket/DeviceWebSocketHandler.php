<?php

namespace App\Services\WebSocket;

use App\Contracts\WebSocketConnection;
use App\Events\DeviceConnected;
use App\Events\DeviceDisconnected;
use App\Models\Device;
use App\Models\PendingCommand;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeviceWebSocketHandler
{
    public function __construct(
        private DeviceConnectionManager $connectionManager,
        private MessageRouter $messageRouter,
        private MessageValidator $messageValidator,
    ) {}

    /**
     * Handle a new device connection after authentication.
     */
    public function onOpen(WebSocketConnection $connection, Device $device): void
    {
        $this->connectionManager->add($connection, $device->id);

        $device->update([
            'connected' => true,
            'last_seen_at' => now(),
        ]);

        DeviceConnected::dispatch($device);

        $connection->send([
            'type' => 'welcome',
            'payload' => [
                'session_id' => (string) Str::uuid(),
                'server_version' => config('app.version', '1.0.0'),
                'capabilities' => [
                    'state_sync',
                    'commands',
                    'streaming',
                ],
            ],
        ]);

        $this->drainPendingCommands($connection, $device);

        Log::info('Device connected', ['device_id' => $device->id]);
    }

    /**
     * Handle an incoming message from a device.
     */
    public function onMessage(WebSocketConnection $connection, string $rawMessage): void
    {
        $deviceId = $this->connectionManager->getDeviceId($connection);

        if (! $deviceId) {
            $connection->send([
                'type' => 'error',
                'payload' => ['code' => 'not_authenticated', 'message' => 'Connection not authenticated'],
            ]);
            $connection->close();

            return;
        }

        $message = json_decode($rawMessage, true);

        if (! is_array($message) || ! isset($message['type'])) {
            $connection->send([
                'type' => 'error',
                'payload' => ['code' => 'invalid_message', 'message' => 'Invalid JSON message format'],
            ]);

            return;
        }

        $validation = $this->messageValidator->validate($message);

        if (! $validation['valid']) {
            $connection->send([
                'type' => 'error',
                'payload' => [
                    'code' => 'validation_error',
                    'message' => 'Message failed schema validation',
                    'details' => $validation['errors'],
                ],
            ]);

            return;
        }

        $device = Device::find($deviceId);

        if (! $device) {
            $connection->send([
                'type' => 'error',
                'payload' => ['code' => 'device_not_found', 'message' => 'Device no longer exists'],
            ]);
            $connection->close();

            return;
        }

        $device->update(['last_seen_at' => now()]);

        $this->messageRouter->route($connection, $device, $message);
    }

    /**
     * Handle a device disconnection.
     */
    public function onClose(WebSocketConnection $connection): void
    {
        $deviceId = $this->connectionManager->remove($connection);

        if ($deviceId) {
            $device = Device::find($deviceId);

            if ($device) {
                $device->update(['connected' => false]);
                DeviceDisconnected::dispatch($device);
                Log::info('Device disconnected', ['device_id' => $deviceId]);
            }
        }
    }

    /**
     * Handle a connection error.
     */
    public function onError(WebSocketConnection $connection, \Throwable $exception): void
    {
        $deviceId = $this->connectionManager->getDeviceId($connection);

        Log::error('WebSocket error', [
            'device_id' => $deviceId,
            'error' => $exception->getMessage(),
        ]);

        $this->onClose($connection);
        $connection->close();
    }

    /**
     * Forward a single pending command to the device in protocol envelope format.
     */
    public function forwardCommand(WebSocketConnection $connection, Device $device, PendingCommand $command): void
    {
        $connection->send([
            'type' => $command->type,
            'id' => $command->message_id,
            'device_id' => (string) $device->id,
            'timestamp' => now()->toIso8601String(),
            'payload' => $command->payload ?? (object) [],
        ]);

        $command->delete();
    }

    /**
     * Drain pending commands to the device (oldest first).
     */
    private function drainPendingCommands(WebSocketConnection $connection, Device $device): void
    {
        $commands = $device->pendingCommands()->orderBy('created_at', 'asc')->get();

        foreach ($commands as $command) {
            $this->forwardCommand($connection, $device, $command);
        }
    }
}
