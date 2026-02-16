<?php

use App\Events\ChiefMessageReceived;
use App\Events\DeviceDisconnected;
use App\Jobs\SendDeviceOfflinePush;
use App\Jobs\SendPushNotification;
use App\Models\DeviceAuthorization;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('run_complete message dispatches push notification job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);
    PushSubscription::factory()->create(['user_id' => $user->id]);

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

    Queue::assertPushed(SendPushNotification::class, function ($job) use ($user) {
        return $job->user->id === $user->id
            && $job->payload['title'] === 'Run completed';
    });
});

test('run_complete with failed status sends failure notification', function () {
    Queue::fake();

    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);
    PushSubscription::factory()->create(['user_id' => $user->id]);

    $event = new ChiefMessageReceived($device->id, $user->id, [
        'type' => 'run_complete',
        'payload' => [
            'project_name' => 'My Project',
            'project_slug' => 'my-project',
            'status' => 'failed',
        ],
    ]);

    event($event);

    Queue::assertPushed(SendPushNotification::class, function ($job) {
        return $job->payload['title'] === 'Run failed';
    });
});

test('run_paused message dispatches push notification job', function () {
    Queue::fake();

    $user = User::factory()->create();
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

    Queue::assertPushed(SendPushNotification::class, function ($job) {
        return $job->payload['title'] === 'Run paused'
            && str_contains($job->payload['body'], 'quota exhausted');
    });
});

test('quota_exhausted message dispatches push notification job', function () {
    Queue::fake();

    $user = User::factory()->create();
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

    Queue::assertPushed(SendPushNotification::class, function ($job) {
        return $job->payload['title'] === 'Run paused'
            && str_contains($job->payload['body'], 'quota exhausted');
    });
});

test('non-notifiable message types do not dispatch push notification', function () {
    Queue::fake();

    $user = User::factory()->create();
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

    Queue::assertNotPushed(SendPushNotification::class);
});

test('device disconnect schedules delayed offline push notification', function () {
    Queue::fake();

    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);

    $event = new DeviceDisconnected($device->id, $user->id);
    event($event);

    Queue::assertPushed(SendDeviceOfflinePush::class, function ($job) use ($device, $user) {
        return $job->deviceId === $device->id
            && $job->userId === $user->id;
    });
});

test('offline push notification skipped if device reconnected', function () {
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'is_online' => true,
    ]);

    Queue::fake();

    $job = new SendDeviceOfflinePush($device->id, $user->id);
    $job->handle();

    // Since the device is online, no SendPushNotification should be dispatched
    Queue::assertNotPushed(SendPushNotification::class);
});

test('offline push notification sent if device still offline', function () {
    Queue::fake();

    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->offline()->create([
        'user_id' => $user->id,
        'is_online' => false,
    ]);

    $job = new SendDeviceOfflinePush($device->id, $user->id);
    $job->handle();

    Queue::assertPushed(SendPushNotification::class, function ($job) {
        return $job->payload['title'] === 'Server offline';
    });
});

test('push notification job respects user push preference', function () {
    $user = User::factory()->create([
        'notification_preferences' => ['push' => false, 'email' => true],
    ]);
    PushSubscription::factory()->create(['user_id' => $user->id]);

    // The job should not send when push is disabled
    $job = new SendPushNotification($user, [
        'title' => 'Test',
        'body' => 'Test notification',
    ]);

    // No exception — the job silently skips
    $job->handle(app(\App\Services\WebPushService::class));

    // If push was disabled, subscriptions should still be there (not cleaned up)
    expect($user->pushSubscriptions()->count())->toBe(1);
});

test('push notification includes deep link url', function () {
    Queue::fake();

    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
    ]);

    $event = new ChiefMessageReceived($device->id, $user->id, [
        'type' => 'run_complete',
        'payload' => [
            'project_name' => 'My App',
            'project_slug' => 'my-app',
            'status' => 'completed',
            'stories_completed' => 3,
            'stories_total' => 3,
        ],
    ]);

    event($event);

    Queue::assertPushed(SendPushNotification::class, function ($job) {
        return $job->payload['data']['url'] === '/projects/my-app/run';
    });
});

test('push notification includes server name', function () {
    Queue::fake();

    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->online()->create([
        'user_id' => $user->id,
        'device_name' => 'hetzner-vps-1',
    ]);

    $event = new ChiefMessageReceived($device->id, $user->id, [
        'type' => 'run_complete',
        'payload' => [
            'project_name' => 'My App',
            'project_slug' => 'my-app',
            'status' => 'completed',
        ],
    ]);

    event($event);

    Queue::assertPushed(SendPushNotification::class, function ($job) {
        return $job->payload['data']['server'] === 'hetzner-vps-1';
    });
});

test('push notification job skipped when user has no subscriptions', function () {
    $user = User::factory()->create([
        'notification_preferences' => ['push' => true],
    ]);

    // No subscriptions created

    $job = new SendPushNotification($user, [
        'title' => 'Test',
        'body' => 'Test notification',
    ]);

    // Should not throw — just skip
    $job->handle(app(\App\Services\WebPushService::class));

    expect($user->pushSubscriptions()->count())->toBe(0);
});

test('preferences page is accessible', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('preferences.edit'));

    $response->assertOk();
});

test('preferences page requires authentication', function () {
    $response = $this->get(route('preferences.edit'));

    $response->assertRedirect(route('login'));
});

test('account deletion removes push subscriptions', function () {
    $user = User::factory()->create();
    PushSubscription::factory()->count(2)->create(['user_id' => $user->id]);

    expect(PushSubscription::where('user_id', $user->id)->count())->toBe(2);

    $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'username' => $user->github_username,
        ]);

    expect(PushSubscription::where('user_id', $user->id)->count())->toBe(0);
});
