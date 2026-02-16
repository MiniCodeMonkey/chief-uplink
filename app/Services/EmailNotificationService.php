<?php

namespace App\Services;

use App\Jobs\SendEmailNotificationDigest;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

class EmailNotificationService
{
    /**
     * Batch window in seconds.
     */
    protected const BATCH_WINDOW = 300; // 5 minutes

    /**
     * Queue an email notification event for batching.
     *
     * Events are accumulated in Redis for 5 minutes, then sent as a single digest email.
     *
     * @param  array<string, mixed>  $event
     */
    public function queue(User $user, array $event): void
    {
        // Check if user has email and email notifications enabled
        if (! $user->email) {
            return;
        }

        $preferences = $user->notification_preferences ?? [];
        if (! ($preferences['email'] ?? true)) {
            return;
        }

        $batchKey = "email:batch:{$user->id}";
        $timerKey = "email:batch:timer:{$user->id}";

        $redis = Redis::connection();

        // Add event to the batch list
        $redis->rpush($batchKey, [json_encode($event)]);
        $redis->expire($batchKey, self::BATCH_WINDOW + 60); // Extra 60s buffer

        // If no timer is set, schedule the digest job
        if (! $redis->exists($timerKey)) {
            $redis->setex($timerKey, self::BATCH_WINDOW, '1');
            SendEmailNotificationDigest::dispatch($user->id)
                ->delay(now()->addSeconds(self::BATCH_WINDOW));
        }
    }

    /**
     * Flush the batch and return accumulated events.
     *
     * @return array<int, array<string, mixed>>
     */
    public function flush(int $userId): array
    {
        $batchKey = "email:batch:{$userId}";
        $timerKey = "email:batch:timer:{$userId}";

        $redis = Redis::connection();

        // Get all events
        $raw = $redis->lrange($batchKey, 0, -1);

        // Clean up
        $redis->del([$batchKey, $timerKey]);

        $events = [];
        foreach ($raw as $item) {
            $decoded = json_decode($item, true);
            if ($decoded) {
                $events[] = $decoded;
            }
        }

        return $events;
    }
}
