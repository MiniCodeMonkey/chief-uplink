<?php

namespace App\Jobs;

use App\Mail\NotificationDigest;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

class SendEmailNotificationDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $userId,
    ) {}

    public function handle(EmailNotificationService $emailService): void
    {
        $user = User::find($this->userId);
        if (! $user || ! $user->email) {
            return;
        }

        // Check email preference
        $preferences = $user->notification_preferences ?? [];
        if (! ($preferences['email'] ?? true)) {
            return;
        }

        // Rate limit: max 10 emails per user per day
        $rateLimitKey = 'email-notifications:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            return;
        }

        $events = $emailService->flush($user->id);
        if (empty($events)) {
            return;
        }

        RateLimiter::hit($rateLimitKey, 86400); // 24 hours

        $unsubscribeUrl = URL::signedRoute('email-unsubscribe', ['user' => $user->id]);

        Mail::to($user->email)->send(new NotificationDigest($events, $unsubscribeUrl));
    }
}
