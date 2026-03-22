<?php

use App\Models\PushSubscription;
use App\Models\User;

describe('POST /api/push-subscriptions', function () {
    it('stores a push subscription for authenticated user', function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-token',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id']);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'p256dh_key' => 'test-p256dh-key',
            'auth_token' => 'test-auth-token',
        ]);
    });

    it('updates existing subscription for same endpoint', function () {
        $user = User::factory()->create();
        PushSubscription::factory()->create([
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/existing',
            'p256dh_key' => 'old-key',
            'auth_token' => 'old-token',
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/existing',
            'keys' => [
                'p256dh' => 'new-key',
                'auth' => 'new-token',
            ],
        ]);

        $response->assertCreated();

        expect(PushSubscription::where('endpoint', 'https://fcm.googleapis.com/fcm/send/existing')->count())->toBe(1);
        expect(PushSubscription::where('endpoint', 'https://fcm.googleapis.com/fcm/send/existing')->first()->p256dh_key)->toBe('new-key');
    });

    it('rejects unauthenticated requests', function () {
        $response = $this->postJson('/api/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => [
                'p256dh' => 'test-key',
                'auth' => 'test-token',
            ],
        ]);

        $response->assertUnauthorized();
    });

    it('validates required fields', function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/push-subscriptions', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    });

    it('validates endpoint is a url', function () {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/push-subscriptions', [
            'endpoint' => 'not-a-url',
            'keys' => [
                'p256dh' => 'test-key',
                'auth' => 'test-token',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint']);
    });
});

describe('DELETE /api/push-subscriptions', function () {
    it('deletes a push subscription by endpoint', function () {
        $user = User::factory()->create();
        PushSubscription::factory()->create([
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete',
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson('/api/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete',
        ]);

        $response->assertNoContent();

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete',
        ]);
    });

    it('only deletes subscriptions owned by the authenticated user', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        PushSubscription::factory()->create([
            'user_id' => $otherUser->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/other-user',
        ]);

        $this->actingAs($user);

        $this->deleteJson('/api/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/other-user',
        ]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $otherUser->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/other-user',
        ]);
    });

    it('rejects unauthenticated requests', function () {
        $response = $this->deleteJson('/api/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        ]);

        $response->assertUnauthorized();
    });
});
