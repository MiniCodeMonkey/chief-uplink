<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('404 error renders Inertia error page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/this-page-does-not-exist');

    $response->assertStatus(404);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Error')
        ->where('status', 404)
    );
});

test('flash error is shared via Inertia', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['error' => 'Something went wrong'])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('flash.error', 'Something went wrong')
        );
});

test('flash success is shared via Inertia', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['success' => 'Operation completed'])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('flash.success', 'Operation completed')
        );
});

test('form validation errors include proper error keys', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => '', // Empty name should fail validation (required)
        'email' => 'not-an-email', // Invalid email format
    ]);

    $response->assertSessionHasErrors(['name', 'email']);
});

test('flash error is null when no error in session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('flash.error', null)
        );
});
