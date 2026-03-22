<?php

use App\Enums\ThemePreference;
use App\Models\User;

it('renders profile settings page for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Settings/Profile')
        ->has('user')
        ->where('user.name', $user->name)
        ->where('user.email', $user->email)
        ->where('user.theme_preference', ThemePreference::System->value)
    );
});

it('redirects unauthenticated users from settings page', function () {
    $this->get('/settings')->assertRedirect('/login');
});

it('updates user profile name and email', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/profile', [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Profile updated.');

    $user->refresh();
    expect($user->name)->toBe('Updated Name');
    expect($user->email)->toBe('updated@example.com');
});

it('validates profile name is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/profile', [
        'name' => '',
        'email' => $user->email,
    ]);

    $response->assertSessionHasErrors('name');
});

it('validates email is unique for other users', function () {
    $existingUser = User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/profile', [
        'name' => $user->name,
        'email' => 'taken@example.com',
    ]);

    $response->assertSessionHasErrors('email');
});

it('allows user to keep their own email', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/profile', [
        'name' => 'New Name',
        'email' => $user->email,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

it('updates theme preference to dark', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/theme', [
        'theme_preference' => 'dark',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Theme preference updated.');

    $user->refresh();
    expect($user->theme_preference)->toBe(ThemePreference::Dark);
});

it('updates theme preference to light', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/theme', [
        'theme_preference' => 'light',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->theme_preference)->toBe(ThemePreference::Light);
});

it('updates theme preference to system', function () {
    $user = User::factory()->create(['theme_preference' => ThemePreference::Dark]);

    $response = $this->actingAs($user)->put('/settings/theme', [
        'theme_preference' => 'system',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->theme_preference)->toBe(ThemePreference::System);
});

it('rejects invalid theme preference', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/theme', [
        'theme_preference' => 'invalid',
    ]);

    $response->assertSessionHasErrors('theme_preference');
});

it('shares theme preference in auth props', function () {
    $user = User::factory()->create(['theme_preference' => ThemePreference::Dark]);

    $response = $this->actingAs($user)->get('/settings');

    $response->assertInertia(fn ($page) => $page
        ->where('auth.user.theme_preference', 'dark')
    );
});
