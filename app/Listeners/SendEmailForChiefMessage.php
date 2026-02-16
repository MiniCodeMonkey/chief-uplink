<?php

namespace App\Listeners;

use App\Events\ChiefMessageReceived;
use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\EmailNotificationService;

class SendEmailForChiefMessage
{
    /**
     * Message types that trigger email notifications.
     */
    protected const NOTIFIABLE_TYPES = [
        'run_complete',
        'run_paused',
        'quota_exhausted',
    ];

    public function __construct(
        protected EmailNotificationService $emailService,
    ) {}

    public function handle(ChiefMessageReceived $event): void
    {
        $type = $event->message['type'] ?? null;

        if (! in_array($type, self::NOTIFIABLE_TYPES)) {
            return;
        }

        $user = User::find($event->userId);
        if (! $user) {
            return;
        }

        $device = DeviceAuthorization::find($event->deviceId);
        $serverName = $device?->device_name ?? 'Unknown server';
        $payload = $event->message['payload'] ?? [];
        $projectName = $payload['project_name'] ?? $payload['project_slug'] ?? 'Unknown project';
        $projectSlug = $payload['project_slug'] ?? '';

        $emailEvent = match ($type) {
            'run_complete' => $this->buildRunCompleteEvent($payload, $projectName, $serverName, $projectSlug),
            'run_paused' => $this->buildRunPausedEvent($payload, $projectName, $serverName, $projectSlug),
            'quota_exhausted' => $this->buildQuotaExhaustedEvent($projectName, $serverName, $projectSlug),
            default => null,
        };

        if ($emailEvent) {
            $this->emailService->queue($user, $emailEvent);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildRunCompleteEvent(array $payload, string $projectName, string $serverName, string $projectSlug): array
    {
        $status = $payload['status'] ?? 'completed';
        $storiesCompleted = $payload['stories_completed'] ?? 0;
        $storiesTotal = $payload['stories_total'] ?? 0;

        $isSuccess = $status === 'completed';
        $title = $isSuccess ? 'Run completed' : 'Run failed';
        $body = $isSuccess
            ? "{$projectName} — {$storiesCompleted}/{$storiesTotal} stories completed"
            : "{$projectName} — run failed";

        return [
            'title' => $title,
            'body' => $body,
            'server' => $serverName,
            'url' => "/projects/{$projectSlug}/run",
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildRunPausedEvent(array $payload, string $projectName, string $serverName, string $projectSlug): array
    {
        $reason = $payload['reason'] ?? 'paused';

        return [
            'title' => 'Run paused',
            'body' => "{$projectName} — {$reason}",
            'server' => $serverName,
            'url' => "/projects/{$projectSlug}/run",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildQuotaExhaustedEvent(string $projectName, string $serverName, string $projectSlug): array
    {
        return [
            'title' => 'Run paused',
            'body' => "{$projectName} — quota exhausted",
            'server' => $serverName,
            'url' => "/projects/{$projectSlug}/run",
        ];
    }
}
