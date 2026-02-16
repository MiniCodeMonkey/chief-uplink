<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    protected ?WebPush $webPush = null;

    protected function getWebPush(): WebPush
    {
        if ($this->webPush === null) {
            $auth = [
                'VAPID' => [
                    'subject' => config('webpush.vapid.subject'),
                    'publicKey' => config('webpush.vapid.public_key'),
                    'privateKey' => config('webpush.vapid.private_key'),
                ],
            ];

            $this->webPush = new WebPush($auth);
            $this->webPush->setReuseVAPIDHeaders(true);
        }

        return $this->webPush;
    }

    /**
     * Send a push notification to all of a user's subscriptions.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sendToUser(User $user, array $payload): void
    {
        $subscriptions = $user->pushSubscriptions;

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = $this->getWebPush();
        $jsonPayload = json_encode($payload);

        foreach ($subscriptions as $pushSubscription) {
            $subscription = Subscription::create([
                'endpoint' => $pushSubscription->endpoint,
                'publicKey' => $pushSubscription->p256dh_key,
                'authToken' => $pushSubscription->auth_token,
                'contentEncoding' => $pushSubscription->content_encoding,
            ]);

            $webPush->queueNotification($subscription, $jsonPayload);
        }

        $this->processResults($webPush);
    }

    protected function processResults(WebPush $webPush): void
    {
        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                $endpoint = $report->getEndpoint();
                $statusCode = $report->getResponse()?->getStatusCode();

                // 404 or 410 means the subscription is no longer valid
                if (in_array($statusCode, [404, 410])) {
                    PushSubscription::where('endpoint', $endpoint)->delete();

                    Log::info('Removed expired push subscription', [
                        'endpoint' => $endpoint,
                    ]);
                } else {
                    Log::warning('Push notification failed', [
                        'endpoint' => $endpoint,
                        'status' => $statusCode,
                        'reason' => $report->getReason(),
                    ]);
                }
            }
        }
    }
}
