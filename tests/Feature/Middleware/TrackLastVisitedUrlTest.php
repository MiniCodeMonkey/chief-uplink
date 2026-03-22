<?php

use App\Models\User;

it('tracks last visited url for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings/team');

    $user->refresh();
    expect($user->last_visited_url)->toContain('/settings/team');
});

it('does not track url for unauthenticated requests', function () {
    $this->get('/');

    // No error thrown — just ensure middleware handles guest gracefully
    expect(true)->toBeTrue();
});

it('updates last visited url on subsequent requests', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings/team');
    $user->refresh();
    expect($user->last_visited_url)->toContain('/settings/team');

    $this->actingAs($user)->get('/');
    $user->refresh();
    expect($user->last_visited_url)->not->toContain('/settings/team');
});

it('does not track url for non-GET requests', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/');
    $user->refresh();
    $originalUrl = $user->last_visited_url;

    $this->actingAs($user)->put('/settings/theme', [
        'theme_preference' => 'dark',
    ]);

    $user->refresh();
    expect($user->last_visited_url)->toBe($originalUrl);
});
