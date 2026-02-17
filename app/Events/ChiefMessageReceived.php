<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChiefMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public readonly int $deviceId,
        public readonly int $userId,
        public readonly array $message,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("device.{$this->deviceId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chief.message';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->deviceId,
            'type' => $this->message['type'] ?? 'unknown',
            'payload' => $this->message['payload'] ?? null,
            'message' => $this->message,
        ];
    }
}
