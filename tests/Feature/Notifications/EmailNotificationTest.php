<?php

use App\Events\ChiefMessageReceived;
use App\Events\DeviceDisconnected;
use App\Jobs\SendDeviceOfflineEmail;
use App\Jobs\SendEmailNotificationDigest;
use App\Mail\NotificationDigest;
use App\Models\DeviceAuthorization;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

function flushEmailBatchKeys(): void
{
    $redis = Redis::connection();
    // Delete any keys with known patterns (IDs 1-100 covers test scenarios)
    for ($i = 1; $i <= 100; $i++) {
        $redis->del(["email:batch:{$i}", "email:batch:timer:{$i}"]);
    }
}

beforeEach(function () {
    flushEmailBatchKeys();
});

test('run_complete message queues email notification event', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);

    $event = new ChiefMessageReceived($device->id, $user->id, [
        'type' => 'run_complete',
        'payload' => [
            'project_name' => 'My Project',
            'project_slug' => 'my-project',
            'status' => 'completed',
            'stories_completed' => 5,
            'stories_total' => 5,
        ],
    ]);

    event($event);

    Queue::assertPushed(SendEmailNotificationDigest::class, function ($job) use ($user) {
        return $job->userId === $user->id;
    });
});

test('run_complete with failed status queues email notification', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);

    $event = new ChiefMessageReceived($device->id, $user->id, [
        'type' => 'run_complete',
        'payload' => [
            'project_name' => 'My Project',
            'project_slug' => 'my-project',
            'status' => 'failed',
        ],
    ]);

    event($event);

    Queue::assertPushed(SendEmailNotificationDigest::class);
});

test('run_paused message queues email notification', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);

    $event = new ChiefMessageReceived($device->id, $user->id, [
        'type' => 'run_paused',
        'payload' => [
            'project_name' => 'My Project',
            'project_slug' => 'my-project',
            'reason' => 'quota exhausted',
        ],
    ]);

    event($event);

    Queue::assertPushed(SendEmailNotificationDigest::class);
});

test('quota_exhausted message queues email notification', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);

    $event = new ChiefMessageReceived($device->id, $user->id, [
        'type' => 'quota_exhausted',
        'payload' => [
            'project_name' => 'My Project',
            'project_slug' => 'my-project',
        ],
    ]);

    event($event);

    Queue::assertPushed(SendEmailNotificationDigest::class);
});

test('non-notifiable message types do not queue email notification', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);

    $nonNotifiableTypes = ['claude_output', 'run_progress', 'prd_output', 'clone_progress'];

    foreach ($nonNotifiableTypes as $type) {
        $event = new ChiefMessageReceived($device->id, $user->id, [
            'type' => $type,
            'payload' => [],
        ]);
        event($event);
    }

    Queue::assertNotPushed(SendEmailNotificationDigest::class);
});

test('email notification skipped when user has no email', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => null]);
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);

    $event = new ChiefMessageReceived($device->id, $user->id, [
        'type' => 'run_complete',
        'payload' => [
            'project_name' => 'My Project',
            'project_slug' => 'my-project',
            'status' => 'completed',
        ],
    ]);

    event($event);

    Queue::assertNotPushed(SendEmailNotificationDigest::class);
});

test('email notification skipped when user has email notifications disabled', function () {
    Queue::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
        'notification_preferences' => ['email' => false, 'push' => true],
    ]);
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);

    $event = new ChiefMessageReceived($device->id, $user->id, [
        'type' => 'run_complete',
        'payload' => [
            'project_name' => 'My Project',
            'project_slug' => 'my-project',
            'status' => 'completed',
        ],
    ]);

    event($event);

    Queue::assertNotPushed(SendEmailNotificationDigest::class);
});

test('device disconnect schedules delayed offline email notification', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);

    $event = new DeviceDisconnected($device->id, $user->id);
    event($event);

    Queue::assertPushed(SendDeviceOfflineEmail::class, function ($job) use ($device, $user) {
        return $job->deviceId === $device->id
            && $job->userId === $user->id;
    });
});

test('offline email notification skipped if device reconnected', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'is_online' => true,
    ]);

    $job = new SendDeviceOfflineEmail($device->id, $user->id);
    $job->handle(app(EmailNotificationService::class));

    // Since the device is online, no email digest job should be dispatched
    Queue::assertNotPushed(SendEmailNotificationDigest::class);
});

test('offline email notification queued if device still offline', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $device = DeviceAuthorization::factory()->offline()->create([
        'user_id' => $user->id,
        'is_online' => false,
    ]);

    $job = new SendDeviceOfflineEmail($device->id, $user->id);
    $job->handle(app(EmailNotificationService::class));

    Queue::assertPushed(SendEmailNotificationDigest::class);
});

test('email digest job sends email with batched events', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);

    // Manually add events to batch
    $redis = Redis::connection();
    $batchKey = "email:batch:{$user->id}";
    $redis->rpush($batchKey, [
        json_encode(['title' => 'Run completed', 'body' => 'My Project — 5/5 stories completed', 'server' => 'vps-1', 'url' => '/projects/my-project/run']),
        json_encode(['title' => 'Run failed', 'body' => 'Other Project — run failed', 'server' => 'vps-1', 'url' => '/projects/other-project/run']),
    ]);

    $job = new SendEmailNotificationDigest($user->id);
    $job->handle(app(EmailNotificationService::class));

    Mail::assertSent(NotificationDigest::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email)
            && count($mail->events) === 2;
    });
});

test('email digest job skips when user has email disabled', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
        'notification_preferences' => ['email' => false],
    ]);

    $redis = Redis::connection();
    $batchKey = "email:batch:{$user->id}";
    $redis->rpush($batchKey, [
        json_encode(['title' => 'Test', 'body' => 'Test body']),
    ]);

    $job = new SendEmailNotificationDigest($user->id);
    $job->handle(app(EmailNotificationService::class));

    Mail::assertNothingSent();
});

test('email digest job skips when batch is empty', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);

    $job = new SendEmailNotificationDigest($user->id);
    $job->handle(app(EmailNotificationService::class));

    Mail::assertNothingSent();
});

test('email batching accumulates events in redis', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $emailService = app(EmailNotificationService::class);

    $emailService->queue($user, ['title' => 'Event 1', 'body' => 'Body 1']);
    $emailService->queue($user, ['title' => 'Event 2', 'body' => 'Body 2']);

    $events = $emailService->flush($user->id);

    expect($events)->toHaveCount(2);
    expect($events[0]['title'])->toBe('Event 1');
    expect($events[1]['title'])->toBe('Event 2');
});

test('email batching only dispatches one digest job per batch window', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);
    $emailService = app(EmailNotificationService::class);

    $emailService->queue($user, ['title' => 'Event 1', 'body' => 'Body 1']);
    $emailService->queue($user, ['title' => 'Event 2', 'body' => 'Body 2']);
    $emailService->queue($user, ['title' => 'Event 3', 'body' => 'Body 3']);

    // Only one digest job should be dispatched (the first queue call)
    Queue::assertPushed(SendEmailNotificationDigest::class, 1);
});

test('email unsubscribe route disables email notifications', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'notification_preferences' => ['email' => true, 'push' => true],
    ]);

    $url = \Illuminate\Support\Facades\URL::signedRoute('email-unsubscribe', ['user' => $user->id]);

    $response = $this->get($url);

    $response->assertRedirect(route('login'));
    $user->refresh();
    expect($user->notification_preferences['email'])->toBeFalse();
    expect($user->notification_preferences['push'])->toBeTrue();
});

test('email unsubscribe rejects invalid signature', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'notification_preferences' => ['email' => true],
    ]);

    $response = $this->get("/email/unsubscribe/{$user->id}?signature=invalid");

    $response->assertForbidden();
});

test('notification preferences can be updated via api', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'notification_preferences' => ['email' => true, 'push' => true],
    ]);

    $response = $this
        ->actingAs($user)
        ->patchJson(route('notification-preferences.update'), [
            'email' => false,
        ]);

    $response->assertOk();
    $user->refresh();
    expect($user->notification_preferences['email'])->toBeFalse();
});

test('notification preferences update requires authentication', function () {
    $response = $this->patchJson('/settings/notification-preferences', [
        'email' => false,
    ]);

    $response->assertUnauthorized();
});

test('notification preferences update validates email field', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $response = $this
        ->actingAs($user)
        ->patchJson(route('notification-preferences.update'), [
            'email' => 'not-a-boolean',
        ]);

    $response->assertUnprocessable();
});

test('email digest has correct subject for single event', function () {
    $mailable = new NotificationDigest(
        [['title' => 'Run completed', 'body' => 'Test', 'server' => 'vps-1', 'url' => '/']],
        'https://example.com/unsubscribe',
    );

    expect($mailable->envelope()->subject)->toBe('Run completed');
});

test('email digest has correct subject for multiple events', function () {
    $mailable = new NotificationDigest(
        [
            ['title' => 'Run completed', 'body' => 'Test', 'server' => 'vps-1', 'url' => '/'],
            ['title' => 'Run failed', 'body' => 'Test', 'server' => 'vps-1', 'url' => '/'],
            ['title' => 'Server offline', 'body' => 'Test', 'server' => 'vps-2', 'url' => '/'],
        ],
        'https://example.com/unsubscribe',
    );

    expect($mailable->envelope()->subject)->toBe('3 notifications from Chief');
});

test('email digest includes unsubscribe url', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);

    $redis = Redis::connection();
    $batchKey = "email:batch:{$user->id}";
    $redis->rpush($batchKey, [
        json_encode(['title' => 'Test', 'body' => 'Test body', 'server' => 'vps', 'url' => '/']),
    ]);

    $job = new SendEmailNotificationDigest($user->id);
    $job->handle(app(EmailNotificationService::class));

    Mail::assertSent(NotificationDigest::class, function ($mail) {
        return ! empty($mail->unsubscribeUrl);
    });
});
