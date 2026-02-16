<?php

use App\Models\CloudDeployment;
use App\Models\DeviceAuthorization;
use App\Models\OauthDeviceCode;
use App\Models\ProviderApiKey;
use App\Models\PushSubscription;
use App\Models\User;

test('user has device authorizations relationship', function () {
    $user = User::factory()->create();
    $device = DeviceAuthorization::factory()->for($user)->create();

    expect($user->deviceAuthorizations)->toHaveCount(1);
    expect($user->deviceAuthorizations->first()->id)->toBe($device->id);
});

test('user has cloud deployments relationship', function () {
    $user = User::factory()->create();
    CloudDeployment::factory()->for($user)->create();

    expect($user->cloudDeployments)->toHaveCount(1);
});

test('user has oauth device codes relationship', function () {
    $user = User::factory()->create();
    OauthDeviceCode::factory()->approved()->create(['user_id' => $user->id]);

    expect($user->oauthDeviceCodes)->toHaveCount(1);
});

test('user has provider api keys relationship', function () {
    $user = User::factory()->create();
    ProviderApiKey::factory()->for($user)->create();

    expect($user->providerApiKeys)->toHaveCount(1);
});

test('user has push subscriptions relationship', function () {
    $user = User::factory()->create();
    PushSubscription::factory()->for($user)->create();

    expect($user->pushSubscriptions)->toHaveCount(1);
});

test('user casts notification_preferences to array', function () {
    $user = User::factory()->create([
        'notification_preferences' => ['push' => true, 'email' => false],
    ]);

    $fresh = $user->fresh();
    expect($fresh->notification_preferences)->toBeArray();
    expect($fresh->notification_preferences['push'])->toBeTrue();
    expect($fresh->notification_preferences['email'])->toBeFalse();
});

test('user casts email_verified_at to datetime', function () {
    $user = User::factory()->create();
    expect($user->email_verified_at)->toBeInstanceOf(\DateTimeInterface::class);
});

test('user hides sensitive attributes in serialization', function () {
    $user = User::factory()->create();
    $array = $user->toArray();

    expect($array)->not->toHaveKey('password');
    expect($array)->not->toHaveKey('two_factor_secret');
    expect($array)->not->toHaveKey('two_factor_recovery_codes');
    expect($array)->not->toHaveKey('remember_token');
});

test('user uses soft deletes', function () {
    $user = User::factory()->create();
    $user->delete();

    $this->assertSoftDeleted('users', ['id' => $user->id]);
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

test('user factory creates valid user with github oauth', function () {
    $user = User::factory()->create();

    expect($user->github_id)->not->toBeNull();
    expect($user->github_username)->not->toBeNull();
    expect($user->avatar_url)->not->toBeNull();
    expect($user->email)->not->toBeNull();
});

test('user factory withoutEmail creates user without email', function () {
    $user = User::factory()->withoutEmail()->create();

    expect($user->email)->toBeNull();
});

test('user factory withoutGithub creates user without github fields', function () {
    $user = User::factory()->withoutGithub()->create();

    expect($user->github_id)->toBeNull();
    expect($user->github_username)->toBeNull();
    expect($user->avatar_url)->toBeNull();
});

test('user factory unverified creates user with null email_verified_at', function () {
    $user = User::factory()->unverified()->create();

    expect($user->email_verified_at)->toBeNull();
});
