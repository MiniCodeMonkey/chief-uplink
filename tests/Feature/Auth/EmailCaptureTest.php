<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('email capture page is shown after oauth when email is null', function () {
    mockSocialiteDriverWithoutEmail();

    $response = $this->get(route('auth.github.callback'));

    $this->assertAuthenticated();
    $response->assertRedirect(route('email-capture.show'));
});

test('email capture page is skipped when github provides email', function () {
    mockSocialiteDriverWithEmail();

    $response = $this->get(route('auth.github.callback'));

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));
});

test('email capture page renders for user without email', function () {
    $user = User::factory()->withoutEmail()->create();

    $response = $this->actingAs($user)->get(route('email-capture.show'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('auth/EmailCapture'));
});

test('email capture page redirects to dashboard if user already has email', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('email-capture.show'));

    $response->assertRedirect(route('dashboard'));
});

test('user can submit email on capture page', function () {
    $user = User::factory()->withoutEmail()->create();

    $response = $this->actingAs($user)->post(route('email-capture.store'), [
        'email' => 'user@example.com',
    ]);

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->email)->toBe('user@example.com');
});

test('email capture validates format', function () {
    $user = User::factory()->withoutEmail()->create();

    $response = $this->actingAs($user)->post(route('email-capture.store'), [
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors('email');
});

test('email capture validates uniqueness', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->withoutEmail()->create();

    $response = $this->actingAs($user)->post(route('email-capture.store'), [
        'email' => 'taken@example.com',
    ]);

    $response->assertSessionHasErrors('email');
});

test('user can skip email capture', function () {
    $user = User::factory()->withoutEmail()->create();

    $response = $this->actingAs($user)->post(route('email-capture.skip'));

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->email)->toBeNull();
});

test('user without email is redirected to email capture from protected routes', function () {
    $user = User::factory()->withoutEmail()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('email-capture.show'));
});

test('user without email can still access logout', function () {
    $user = User::factory()->withoutEmail()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('user with email can access dashboard normally', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('email can be updated from settings profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'newemail@example.com',
    ]);

    $response->assertSessionHasNoErrors();
    expect($user->fresh()->email)->toBe('newemail@example.com');
});

test('email can be set to null from settings profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => '',
    ]);

    $response->assertSessionHasNoErrors();
    expect($user->fresh()->email)->toBeNull();
});

function mockSocialiteDriverWithoutEmail(): void
{
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = '99999';
    $socialiteUser->nickname = 'privateuser';
    $socialiteUser->name = 'Private User';
    $socialiteUser->email = null;
    $socialiteUser->avatar = 'https://avatars.githubusercontent.com/u/99999';

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

function mockSocialiteDriverWithEmail(): void
{
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = '88888';
    $socialiteUser->nickname = 'publicuser';
    $socialiteUser->name = 'Public User';
    $socialiteUser->email = 'public@example.com';
    $socialiteUser->avatar = 'https://avatars.githubusercontent.com/u/88888';

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
