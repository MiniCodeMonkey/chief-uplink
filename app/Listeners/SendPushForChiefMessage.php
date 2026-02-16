<?php

namespace App\Listeners;

use App\Events\ChiefMessageReceived;
use App\Jobs\SendPushNotification;
use App\Models\DeviceAuthorization;
use App\Models\User;

class SendPushForChiefMessage
{
    /**
     * Message types that trigger push notifications.
     */
    protected const NOTIFIABLE_TYPES = [
        'run_complete',
        'run_paused',
        'quota_exhausted',
    ];

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

        $notification = match ($type) {
            'run_complete' => $this->buildRunCompletePayload($payload, $projectName, $serverName, $projectSlug),
            'run_paused' => $this->buildRunPausedPayload($payload, $projectName, $serverName, $projectSlug),
            'quota_exhausted' => $this->buildQuotaExhaustedPayload($projectName, $serverName, $projectSlug),
            default => null,
        };

        if ($notification) {
            SendPushNotification::dispatch($user, $notification);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildRunCompletePayload(array $payload, string $projectName, string $serverName, string $projectSlug): array
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
            'tag' => "run-{$projectSlug}",
            'data' => [
                'url' => "/projects/{$projectSlug}/run",
                'type' => $isSuccess ? 'success' : 'error',
                'server' => $serverName,
                'project' => $projectName,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildRunPausedPayload(array $payload, string $projectName, string $serverName, string $projectSlug): array
    {
        $reason = $payload['reason'] ?? 'paused';

        return [
            'title' => 'Run paused',
            'body' => "{$projectName} — {$reason}",
            'tag' => "run-{$projectSlug}",
            'data' => [
                'url' => "/projects/{$projectSlug}/run",
                'type' => 'warning',
                'server' => $serverName,
                'project' => $projectName,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildQuotaExhaustedPayload(string $projectName, string $serverName, string $projectSlug): array
    {
        return [
            'title' => 'Run paused',
            'body' => "{$projectName} — quota exhausted",
            'tag' => "run-{$projectSlug}",
            'data' => [
                'url' => "/projects/{$projectSlug}/run",
                'type' => 'warning',
                'server' => $serverName,
                'project' => $projectName,
            ],
        ];
    }
}
