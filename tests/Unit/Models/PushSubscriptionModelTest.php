<?php

use App\Models\PushSubscription;
use App\Models\User;

test('push subscription belongs to user', function () {
    $user = User::factory()->create();
    $sub = PushSubscription::factory()->for($user)->create();

    expect($sub->user->id)->toBe($user->id);
});

test('push subscription factory creates valid record', function () {
    $sub = PushSubscription::factory()->create();

    expect($sub->endpoint)->toStartWith('https://');
    expect($sub->p256dh_key)->not->toBeEmpty();
    expect($sub->auth_token)->not->toBeEmpty();
    expect($sub->content_encoding)->toBe('aesgcm');
});
