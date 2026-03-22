<?php

use App\Enums\TeamRole;
use App\Events\RunUpdated;
use App\Listeners\SendRunCompletedPushNotification;
use App\Models\Device;
use App\Models\Run;
use App\Models\User;
use App\Services\WebPushService;

it('sends push notifications when a run completes', function () {
    $webPush = Mockery::mock(WebPushService::class);
    $webPush->shouldReceive('sendToUsers')
        ->once()
        ->withArgs(function (array $userIds, array $payload) {
            return count($userIds) > 0
                && $payload['title'] === 'Run Completed'
                && str_contains($payload['body'], 'stories completed');
        });

    $owner = User::factory()->create();
    $team = $owner->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $run = Run::factory()->completed()->create(['device_id' => $device->id]);

    $listener = new SendRunCompletedPushNotification($webPush);
    $listener->handle(new RunUpdated($run));
});

it('does not send push notifications for non-completed runs', function () {
    $webPush = Mockery::mock(WebPushService::class);
    $webPush->shouldNotReceive('sendToUsers');

    $owner = User::factory()->create();
    $team = $owner->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $run = Run::factory()->running()->create(['device_id' => $device->id]);

    $listener = new SendRunCompletedPushNotification($webPush);
    $listener->handle(new RunUpdated($run));
});

it('includes all team member user IDs', function () {
    $capturedUserIds = null;

    $webPush = Mockery::mock(WebPushService::class);
    $webPush->shouldReceive('sendToUsers')
        ->once()
        ->withArgs(function (array $userIds, array $payload) use (&$capturedUserIds) {
            $capturedUserIds = $userIds;

            return true;
        });

    $owner = User::factory()->create();
    $team = $owner->currentTeam();

    $member = User::factory()->create();
    $member->teams()->detach();
    $member->ownedTeams()->delete();
    $team->users()->attach($member->id, ['role' => TeamRole::Member->value]);

    $device = Device::factory()->create(['team_id' => $team->id]);
    $run = Run::factory()->completed()->create(['device_id' => $device->id]);

    $listener = new SendRunCompletedPushNotification($webPush);
    $listener->handle(new RunUpdated($run));

    expect($capturedUserIds)->toContain($owner->id)
        ->toContain($member->id)
        ->toHaveCount(2);
});

it('includes run URL in notification payload', function () {
    $capturedPayload = null;

    $webPush = Mockery::mock(WebPushService::class);
    $webPush->shouldReceive('sendToUsers')
        ->once()
        ->withArgs(function (array $userIds, array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        });

    $owner = User::factory()->create();
    $team = $owner->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $run = Run::factory()->completed()->create(['device_id' => $device->id]);

    $listener = new SendRunCompletedPushNotification($webPush);
    $listener->handle(new RunUpdated($run));

    expect($capturedPayload['url'])->toBe("/runs/{$run->id}");
});

it('includes PRD title in notification body', function () {
    $capturedPayload = null;

    $webPush = Mockery::mock(WebPushService::class);
    $webPush->shouldReceive('sendToUsers')
        ->once()
        ->withArgs(function (array $userIds, array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        });

    $owner = User::factory()->create();
    $team = $owner->currentTeam();
    $device = Device::factory()->create(['team_id' => $team->id]);
    $run = Run::factory()->completed()->create(['device_id' => $device->id]);
    $prdTitle = $run->prd->title;

    $listener = new SendRunCompletedPushNotification($webPush);
    $listener->handle(new RunUpdated($run));

    expect($capturedPayload['body'])->toContain($prdTitle);
});
