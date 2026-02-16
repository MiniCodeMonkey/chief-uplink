<?php

namespace App\Console\Commands;

use App\Events\DeviceDisconnected;
use App\Models\DeviceAuthorization;
use App\Services\WebSocketMessageBuffer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckDeviceHeartbeats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'device:check-heartbeats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect stale devices that have missed heartbeats and mark them offline';

    /**
     * Execute the console command.
     */
    public function handle(WebSocketMessageBuffer $messageBuffer): int
    {
        $staleThreshold = now()->subMinutes(2);

        $staleDevices = DeviceAuthorization::where('is_online', true)
            ->where('last_heartbeat_at', '<', $staleThreshold)
            ->get();

        if ($staleDevices->isEmpty()) {
            return Command::SUCCESS;
        }

        $count = 0;

        foreach ($staleDevices as $device) {
            try {
                $messageBuffer->markDisconnected($device->id);
            } catch (\Throwable $e) {
                Log::warning('Failed to mark stale device disconnected in buffer', [
                    'device_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $device->update(['is_online' => false]);

            DeviceDisconnected::dispatch($device->id, $device->user_id);

            $count++;

            Log::info('Stale device marked offline', [
                'device_id' => $device->id,
                'user_id' => $device->user_id,
                'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
            ]);
        }

        $this->info("Marked {$count} stale device(s) offline.");

        return Command::SUCCESS;
    }
}
