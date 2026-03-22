<?php

use App\Models\PushSubscription;
use App\Models\User;

it('belongs to a user', function () {
    $subscription = PushSubscription::factory()->create();

    expect($subscription->user)->toBeInstanceOf(User::class);
});

it('can be created with factory', function () {
    $subscription = PushSubscription::factory()->create();

    expect($subscription)->toBeInstanceOf(PushSubscription::class);
    expect($subscription->endpoint)->toBeString();
    expect($subscription->p256dh_key)->toBeString();
    expect($subscription->auth_token)->toBeString();
});

it('user has push subscriptions relationship', function () {
    $user = User::factory()->create();
    PushSubscription::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->pushSubscriptions)->toHaveCount(2);
    expect($user->pushSubscriptions->first())->toBeInstanceOf(PushSubscription::class);
});
