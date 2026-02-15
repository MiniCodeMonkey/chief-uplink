<?php

use App\Models\CloudDeployment;
use App\Models\DeviceAuthorization;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('email field can be updated from profile settings', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'newemail@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe('newemail@example.com');
});

test('email field can be cleared from profile settings', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => '',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBeNull();
});

test('user can delete their account by typing their username', function () {
    $user = User::factory()->create([
        'github_username' => 'testuser',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'username' => 'testuser',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/login');

    $this->assertGuest();
    $this->assertSoftDeleted($user);
});

test('account deletion requires correct username', function () {
    $user = User::factory()->create([
        'github_username' => 'testuser',
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'username' => 'wrong-username',
        ]);

    $response
        ->assertSessionHasErrors('username')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->deleted_at)->toBeNull();
});

test('account deletion revokes all device authorizations', function () {
    $user = User::factory()->create([
        'github_username' => 'testuser',
    ]);

    $device1 = DeviceAuthorization::factory()->online()->create(['user_id' => $user->id]);
    $device2 = DeviceAuthorization::factory()->online()->create(['user_id' => $user->id]);

    $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'username' => 'testuser',
        ]);

    expect($device1->fresh()->revoked_at)->not->toBeNull();
    expect($device2->fresh()->revoked_at)->not->toBeNull();
});

test('account deletion destroys active cloud deployments', function () {
    $user = User::factory()->create([
        'github_username' => 'testuser',
    ]);

    $deployment = CloudDeployment::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'username' => 'testuser',
        ]);

    expect($deployment->fresh()->status)->toBe('destroyed');
});

test('account deletion removes personal data', function () {
    $user = User::factory()->create([
        'github_username' => 'testuser',
        'email' => 'user@example.com',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/12345',
        'notification_preferences' => ['push' => true],
    ]);

    $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'username' => 'testuser',
        ]);

    $deletedUser = User::withTrashed()->find($user->id);
    expect($deletedUser->email)->toBeNull();
    expect($deletedUser->avatar_url)->toBeNull();
    expect($deletedUser->notification_preferences)->toBeNull();
});

test('account deletion shows status message on login page', function () {
    $user = User::factory()->create([
        'github_username' => 'testuser',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'username' => 'testuser',
        ]);

    $response->assertRedirect('/login');
    $response->assertSessionHas('status', 'Account deleted');
});
