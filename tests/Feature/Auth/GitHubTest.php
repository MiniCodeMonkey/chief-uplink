<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('redirects to github authorization page', function () {
    Socialite::fake('github');

    $response = $this->get('/auth/github');

    $response->assertRedirect();
});

it('creates a new user from github callback', function () {
    Socialite::fake('github', (new SocialiteUser)->map([
        'id' => 'github-123',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'avatar' => 'https://github.com/avatars/jane.png',
    ])->setToken('fake-github-token'));

    $response = $this->get('/auth/github/callback');

    $response->assertRedirect('/');

    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'github_id' => 'github-123',
        'avatar_url' => 'https://github.com/avatars/jane.png',
    ]);

    // Verify default team was created
    $user = User::query()->where('github_id', 'github-123')->first();
    expect($user->teams)->toHaveCount(1);
    expect($user->password)->toBeNull();
});

it('updates github token for existing user with matching github_id', function () {
    $user = User::factory()->create([
        'github_id' => 'github-456',
        'github_token' => 'old-token',
    ]);

    Socialite::fake('github', (new SocialiteUser)->map([
        'id' => 'github-456',
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => 'https://github.com/avatars/updated.png',
    ])->setToken('new-github-token'));

    $response = $this->get('/auth/github/callback');

    $response->assertRedirect('/');

    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->github_token)->toBe('new-github-token');
    expect($user->avatar_url)->toBe('https://github.com/avatars/updated.png');
});

it('links github to existing user with matching email', function () {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'github_id' => null,
        'github_token' => null,
    ]);

    Socialite::fake('github', (new SocialiteUser)->map([
        'id' => 'github-789',
        'name' => $user->name,
        'email' => 'existing@example.com',
        'avatar' => 'https://github.com/avatars/linked.png',
    ])->setToken('linked-github-token'));

    $response = $this->get('/auth/github/callback');

    $response->assertRedirect('/');

    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->github_id)->toBe('github-789');
    expect($user->github_token)->toBe('linked-github-token');
    expect($user->avatar_url)->toBe('https://github.com/avatars/linked.png');
});

it('creates user with nickname when name is null', function () {
    Socialite::fake('github', (new SocialiteUser)->map([
        'id' => 'github-no-name',
        'name' => null,
        'nickname' => 'janedoe',
        'email' => 'noname@example.com',
        'avatar' => null,
    ])->setToken('fake-token'));

    $response = $this->get('/auth/github/callback');

    $response->assertRedirect('/');

    $this->assertDatabaseHas('users', [
        'name' => 'janedoe',
        'email' => 'noname@example.com',
        'github_id' => 'github-no-name',
    ]);
});

it('stores github token encrypted', function () {
    Socialite::fake('github', (new SocialiteUser)->map([
        'id' => 'github-enc',
        'name' => 'Encrypted User',
        'email' => 'encrypted@example.com',
        'avatar' => null,
    ])->setToken('secret-token'));

    $this->get('/auth/github/callback');

    $user = User::query()->where('github_id', 'github-enc')->first();

    // The token should be decrypted when accessed via the model
    expect($user->github_token)->toBe('secret-token');

    // But stored encrypted in the database
    $raw = DB::table('users')
        ->where('github_id', 'github-enc')
        ->value('github_token');

    expect($raw)->not->toBe('secret-token');
});

it('redirects authenticated users away from github redirect', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/auth/github');

    $response->assertRedirect('/');
});
