<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    private WebPush $webPush;

    public function __construct()
    {
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.vapid.subject'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ]);

        $this->webPush->setReuseVAPIDHeaders(true);
    }

    /**
     * Send a push notification to a specific subscription.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sendToSubscription(PushSubscription $pushSubscription, array $payload): bool
    {
        $subscription = Subscription::create([
            'endpoint' => $pushSubscription->endpoint,
            'publicKey' => $pushSubscription->p256dh_key,
            'authToken' => $pushSubscription->auth_token,
        ]);

        $report = $this->webPush->sendOneNotification($subscription, json_encode($payload));

        if ($report->isSuccess()) {
            return true;
        }

        if ($report->isSubscriptionExpired()) {
            $pushSubscription->delete();
        }

        Log::warning('Web push notification failed', [
            'endpoint' => $pushSubscription->endpoint,
            'reason' => $report->getReason(),
        ]);

        return false;
    }

    /**
     * Send a push notification to all subscriptions for given user IDs.
     *
     * @param  array<int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function sendToUsers(array $userIds, array $payload): void
    {
        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $userIds)
            ->get();

        foreach ($subscriptions as $pushSubscription) {
            $this->sendToSubscription($pushSubscription, $payload);
        }
    }
}
