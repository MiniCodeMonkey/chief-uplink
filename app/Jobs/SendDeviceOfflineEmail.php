<?php

namespace App\Jobs;

use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDeviceOfflineEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $deviceId,
        public readonly int $userId,
    ) {}

    public function handle(EmailNotificationService $emailService): void
    {
        $device = DeviceAuthorization::find($this->deviceId);

        // If the device came back online, skip the notification
        if (! $device || $device->is_online) {
            return;
        }

        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $emailService->queue($user, [
            'title' => 'Server offline',
            'body' => "{$device->device_name} went offline unexpectedly",
            'server' => $device->device_name,
            'url' => '/',
        ]);
    }
}
