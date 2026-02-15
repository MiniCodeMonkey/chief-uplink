<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('login screen renders correct inertia component', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('auth/Login'));
});

test('github redirect works', function () {
    $response = $this->get(route('auth.github'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('github.com');
});

test('github callback creates new user', function () {
    mockSocialiteDriver();

    $response = $this->get(route('auth.github.callback'));

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('users', [
        'github_id' => '12345',
        'github_username' => 'testuser',
    ]);
});

test('github callback logs in existing user', function () {
    $existingUser = User::factory()->create([
        'github_id' => '12345',
        'github_username' => 'testuser',
    ]);

    mockSocialiteDriver();

    $response = $this->get(route('auth.github.callback'));

    $this->assertAuthenticatedAs($existingUser);
    $response->assertRedirect(route('dashboard'));
});

test('github callback updates user info on login', function () {
    User::factory()->create([
        'github_id' => '12345',
        'github_username' => 'oldusername',
        'avatar_url' => 'https://old-avatar.com/old.jpg',
    ]);

    mockSocialiteDriver();

    $this->get(route('auth.github.callback'));

    $this->assertDatabaseHas('users', [
        'github_id' => '12345',
        'github_username' => 'testuser',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/12345',
    ]);
});

test('github callback rejects soft-deleted users', function () {
    $user = User::factory()->create([
        'github_id' => '12345',
        'github_username' => 'testuser',
    ]);
    $user->delete();

    mockSocialiteDriver();

    $response = $this->get(route('auth.github.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('unauthenticated users are redirected to login', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated users cannot visit login page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('login'));

    $response->assertRedirect(route('dashboard'));
});

function mockSocialiteDriver(): void
{
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = '12345';
    $socialiteUser->nickname = 'testuser';
    $socialiteUser->name = 'Test User';
    $socialiteUser->email = 'test@example.com';
    $socialiteUser->avatar = 'https://avatars.githubusercontent.com/u/12345';

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(new class($socialiteUser)
        {
            public function __construct(private SocialiteUser $user) {}

            public function user(): SocialiteUser
            {
                return $this->user;
            }
        });
}
