<?php

namespace App\Listeners;

use App\Enums\RunStatus;
use App\Events\RunUpdated;
use App\Services\WebPushService;

class SendRunCompletedPushNotification
{
    public function __construct(private WebPushService $webPushService) {}

    public function handle(RunUpdated $event): void
    {
        if ($event->run->status !== RunStatus::Completed) {
            return;
        }

        $run = $event->run->loadMissing(['device.team.users', 'prd']);

        $team = $run->device->team;
        $userIds = $team->users->pluck('id')->all();

        if (empty($userIds)) {
            return;
        }

        $stories = $run->stories ?? [];
        $completedCount = collect($stories)->where('status', 'done')->count();
        $totalCount = count($stories);

        $duration = '';
        if ($run->started_at && $run->completed_at) {
            $minutes = (int) $run->started_at->diffInMinutes($run->completed_at);
            $duration = $minutes > 0 ? " in {$minutes}m" : ' in <1m';
        }

        $prdName = $run->prd?->title ?? 'Unknown PRD';

        $this->webPushService->sendToUsers($userIds, [
            'title' => 'Run Completed',
            'body' => "{$prdName}: {$completedCount}/{$totalCount} stories completed{$duration}",
            'url' => "/runs/{$run->id}",
            'icon' => '/favicon.ico',
        ]);
    }
}
