<?php

use App\Models\User;

it('renders the login page', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

it('authenticates a user with valid credentials', function () {
    $user = User::factory()->create([
        'password' => 'correct-password',
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
});

it('shows validation error for invalid credentials', function () {
    $user = User::factory()->create([
        'password' => 'correct-password',
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('validates required fields', function () {
    $response = $this->post('/login', []);

    $response->assertSessionHasErrors(['email', 'password']);
});

it('redirects authenticated users away from login page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/login');

    $response->assertRedirect('/');
});
