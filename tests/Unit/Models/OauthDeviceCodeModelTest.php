<?php

use App\Models\OauthDeviceCode;
use App\Models\User;

test('oauth device code belongs to user', function () {
    $user = User::factory()->create();
    $code = OauthDeviceCode::factory()->approved()->create(['user_id' => $user->id]);

    expect($code->user->id)->toBe($user->id);
});

test('oauth device code casts expires_at to datetime', function () {
    $code = OauthDeviceCode::factory()->create();
    expect($code->expires_at)->toBeInstanceOf(\DateTimeInterface::class);
});

test('oauth device code casts last_polled_at to datetime', function () {
    $code = OauthDeviceCode::factory()->create(['last_polled_at' => now()]);
    expect($code->last_polled_at)->toBeInstanceOf(\DateTimeInterface::class);
});

test('isExpired returns true for expired code', function () {
    $code = OauthDeviceCode::factory()->expired()->create();
    expect($code->isExpired())->toBeTrue();
});

test('isExpired returns false for valid code', function () {
    $code = OauthDeviceCode::factory()->create();
    expect($code->isExpired())->toBeFalse();
});

test('isPending returns true for pending code', function () {
    $code = OauthDeviceCode::factory()->create();
    expect($code->isPending())->toBeTrue();
});

test('isPending returns false for approved code', function () {
    $code = OauthDeviceCode::factory()->approved()->create();
    expect($code->isPending())->toBeFalse();
});

test('isPending returns false for denied code', function () {
    $code = OauthDeviceCode::factory()->denied()->create();
    expect($code->isPending())->toBeFalse();
});

test('device code factory generates valid user code format', function () {
    $code = OauthDeviceCode::factory()->create();
    expect($code->user_code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');
});

test('device code factory approved state links to user', function () {
    $code = OauthDeviceCode::factory()->approved()->create();

    expect($code->status)->toBe('approved');
    expect($code->user_id)->not->toBeNull();
});
