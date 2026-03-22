<?php

use App\Models\User;

it('logs out the user and redirects', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/');
    $this->assertGuest();
});

it('requires authentication', function () {
    $response = $this->post('/logout');

    $response->assertRedirect('/login');
});
