<?php

use App\Jobs\SendEmailNotificationDigest;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    // Clean up any leftover Redis keys from previous tests
    $redis = Redis::connection();
    for ($i = 1; $i <= 20; $i++) {
        $redis->del(["email:batch:{$i}", "email:batch:timer:{$i}"]);
    }
});

test('queue skips user without email', function () {
    Queue::fake();

    $user = User::factory()->withoutEmail()->create();
    $service = new EmailNotificationService;

    $service->queue($user, ['type' => 'run_complete', 'project' => 'test']);

    Queue::assertNothingPushed();
});

test('queue skips user with email notifications disabled', function () {
    Queue::fake();

    $user = User::factory()->create([
        'notification_preferences' => ['push' => true, 'email' => false],
    ]);
    $service = new EmailNotificationService;

    $service->queue($user, ['type' => 'run_complete', 'project' => 'test']);

    Queue::assertNothingPushed();
});

test('queue dispatches digest job on first event', function () {
    Queue::fake();

    $user = User::factory()->create();
    $service = new EmailNotificationService;

    $service->queue($user, ['type' => 'run_complete', 'project' => 'test']);

    Queue::assertPushed(SendEmailNotificationDigest::class);
});

test('queue does not dispatch additional digest job for subsequent events', function () {
    Queue::fake();

    $user = User::factory()->create();
    $service = new EmailNotificationService;

    $service->queue($user, ['type' => 'run_complete', 'project' => 'test1']);
    $service->queue($user, ['type' => 'run_failed', 'project' => 'test2']);

    Queue::assertPushed(SendEmailNotificationDigest::class, 1);
});

test('flush returns accumulated events', function () {
    $user = User::factory()->create();
    $service = new EmailNotificationService;

    Queue::fake();

    $service->queue($user, ['type' => 'run_complete', 'project' => 'project-a']);
    $service->queue($user, ['type' => 'run_failed', 'project' => 'project-b']);

    $events = $service->flush($user->id);

    expect($events)->toHaveCount(2);
    expect($events[0]['type'])->toBe('run_complete');
    expect($events[1]['type'])->toBe('run_failed');
});

test('flush clears batch after retrieval', function () {
    $user = User::factory()->create();
    $service = new EmailNotificationService;

    Queue::fake();

    $service->queue($user, ['type' => 'run_complete', 'project' => 'test']);
    $service->flush($user->id);

    $events = $service->flush($user->id);
    expect($events)->toBeEmpty();
});

test('queue respects default email preference when not set', function () {
    Queue::fake();

    $user = User::factory()->create([
        'notification_preferences' => ['push' => true],
    ]);
    $service = new EmailNotificationService;

    $service->queue($user, ['type' => 'run_complete', 'project' => 'test']);

    // Default should be true when not explicitly set
    Queue::assertPushed(SendEmailNotificationDigest::class);
});
