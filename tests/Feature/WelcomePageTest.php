<?php

use App\Models\User;

it('renders Welcome page for unauthenticated visitors', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
        );
});

it('redirects authenticated users to Dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
        );
});

it('redirects authenticated users to last visited url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['last_visited_url' => '/devices'])
        ->get('/')
        ->assertRedirect('/devices');
});
