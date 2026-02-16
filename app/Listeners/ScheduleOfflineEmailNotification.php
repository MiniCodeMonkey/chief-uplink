<?php

namespace App\Listeners;

use App\Events\DeviceDisconnected;
use App\Jobs\SendDeviceOfflineEmail;

class ScheduleOfflineEmailNotification
{
    public function handle(DeviceDisconnected $event): void
    {
        // Delay 2 minutes — if the device reconnects within that time, the job
        // will check and skip sending the notification.
        SendDeviceOfflineEmail::dispatch($event->deviceId, $event->userId)
            ->delay(now()->addMinutes(2));
    }
}
