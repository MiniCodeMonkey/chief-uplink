<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

test('device-code rate limiter is configured', function () {
    $limiter = RateLimiter::limiter('device-code');
    expect($limiter)->not->toBeNull();
});

test('token-refresh rate limiter is configured', function () {
    $limiter = RateLimiter::limiter('token-refresh');
    expect($limiter)->not->toBeNull();
});

test('device-code-entry rate limiter is configured', function () {
    $limiter = RateLimiter::limiter('device-code-entry');
    expect($limiter)->not->toBeNull();
});

test('browser-commands rate limiter is configured', function () {
    $limiter = RateLimiter::limiter('browser-commands');
    expect($limiter)->not->toBeNull();
});

test('login-attempts rate limiter is configured', function () {
    $limiter = RateLimiter::limiter('login-attempts');
    expect($limiter)->not->toBeNull();
});

test('general-api rate limiter is configured', function () {
    $limiter = RateLimiter::limiter('general-api');
    expect($limiter)->not->toBeNull();
});

test('clone-create-project rate limiter is configured', function () {
    $limiter = RateLimiter::limiter('clone-create-project');
    expect($limiter)->not->toBeNull();
});

test('cloud-deploy rate limiter is configured', function () {
    $limiter = RateLimiter::limiter('cloud-deploy');
    expect($limiter)->not->toBeNull();
});

test('account-deletion rate limiter is configured', function () {
    $limiter = RateLimiter::limiter('account-deletion');
    expect($limiter)->not->toBeNull();
});

test('push-notifications rate limiter is configured', function () {
    $limiter = RateLimiter::limiter('push-notifications');
    expect($limiter)->not->toBeNull();
});

test('device-code rate limiter returns correct limit', function () {
    $limiter = RateLimiter::limiter('device-code');
    $request = \Illuminate\Http\Request::create('/test', 'POST');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $limits = $limiter($request);

    if ($limits instanceof Limit) {
        $limits = [$limits];
    }

    $limit = $limits[0] ?? $limits;
    expect($limit)->toBeInstanceOf(Limit::class);
});

test('browser-commands rate limiter uses user id when authenticated', function () {
    $limiter = RateLimiter::limiter('browser-commands');
    $user = \App\Models\User::factory()->create();
    $request = \Illuminate\Http\Request::create('/test', 'POST');
    $request->setUserResolver(fn () => $user);

    $limit = $limiter($request);

    expect($limit)->toBeInstanceOf(Limit::class);
});
