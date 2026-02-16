<?php

use App\Models\PushSubscription;
use App\Models\User;

test('user can subscribe to push notifications', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('push-subscription.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-token',
            ],
        ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Subscription saved']);

    expect(PushSubscription::where('user_id', $user->id)->count())->toBe(1);

    $subscription = PushSubscription::where('user_id', $user->id)->first();
    expect($subscription->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/test-endpoint-123');
    expect($subscription->p256dh_key)->toBe('test-p256dh-key');
    expect($subscription->auth_token)->toBe('test-auth-token');
});

test('subscribing with same endpoint updates existing subscription', function () {
    $user = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint-456';

    PushSubscription::factory()->create([
        'user_id' => $user->id,
        'endpoint' => $endpoint,
        'p256dh_key' => 'old-key',
        'auth_token' => 'old-token',
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('push-subscription.store'), [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'new-key',
                'auth' => 'new-token',
            ],
        ]);

    $response->assertOk();

    expect(PushSubscription::where('user_id', $user->id)->count())->toBe(1);

    $subscription = PushSubscription::where('user_id', $user->id)->first();
    expect($subscription->p256dh_key)->toBe('new-key');
    expect($subscription->auth_token)->toBe('new-token');
});

test('user can unsubscribe from push notifications', function () {
    $user = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint-789';

    PushSubscription::factory()->create([
        'user_id' => $user->id,
        'endpoint' => $endpoint,
    ]);

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('push-subscription.destroy'), [
            'endpoint' => $endpoint,
        ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Subscription removed']);

    expect(PushSubscription::where('user_id', $user->id)->count())->toBe(0);
});

test('unsubscribing only removes own subscription', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/shared-endpoint';

    PushSubscription::factory()->create([
        'user_id' => $otherUser->id,
        'endpoint' => $endpoint,
    ]);

    $this
        ->actingAs($user)
        ->deleteJson(route('push-subscription.destroy'), [
            'endpoint' => $endpoint,
        ]);

    expect(PushSubscription::where('user_id', $otherUser->id)->count())->toBe(1);
});

test('subscription requires valid endpoint', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('push-subscription.store'), [
            'endpoint' => 'not-a-url',
            'keys' => [
                'p256dh' => 'test-key',
                'auth' => 'test-token',
            ],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('endpoint');
});

test('subscription requires keys', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('push-subscription.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
});

test('subscription requires authentication', function () {
    $response = $this->postJson(route('push-subscription.store'), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
        'keys' => [
            'p256dh' => 'test-key',
            'auth' => 'test-token',
        ],
    ]);

    $response->assertUnauthorized();
});

test('unsubscribe requires authentication', function () {
    $response = $this->deleteJson(route('push-subscription.destroy'), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
    ]);

    $response->assertUnauthorized();
});

test('push subscription belongs to user', function () {
    $user = User::factory()->create();
    $subscription = PushSubscription::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($subscription->user->id)->toBe($user->id);
});

test('user has push subscriptions relationship', function () {
    $user = User::factory()->create();
    PushSubscription::factory()->count(3)->create([
        'user_id' => $user->id,
    ]);

    expect($user->pushSubscriptions)->toHaveCount(3);
});

test('content encoding defaults to aesgcm', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->postJson(route('push-subscription.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-enc',
            'keys' => [
                'p256dh' => 'test-key',
                'auth' => 'test-token',
            ],
        ]);

    $subscription = PushSubscription::where('user_id', $user->id)->first();
    expect($subscription->content_encoding)->toBe('aesgcm');
});

test('content encoding accepts aes128gcm', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->postJson(route('push-subscription.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-enc2',
            'keys' => [
                'p256dh' => 'test-key',
                'auth' => 'test-token',
            ],
            'contentEncoding' => 'aes128gcm',
        ]);

    $subscription = PushSubscription::where('user_id', $user->id)->first();
    expect($subscription->content_encoding)->toBe('aes128gcm');
});
