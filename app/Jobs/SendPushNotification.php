<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly User $user,
        public readonly array $payload,
    ) {}

    public function handle(WebPushService $webPushService): void
    {
        // Check if user has push notifications enabled
        $preferences = $this->user->notification_preferences ?? [];
        if (! ($preferences['push'] ?? true)) {
            return;
        }

        // Check if user has any subscriptions
        if ($this->user->pushSubscriptions()->count() === 0) {
            return;
        }

        // Rate limit: max 20 per user per hour
        $rateLimitKey = 'push-notifications:'.$this->user->id;
        $maxAttempts = config('webpush.rate_limit', 20);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return;
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $webPushService->sendToUser($this->user, $this->payload);
    }
}
