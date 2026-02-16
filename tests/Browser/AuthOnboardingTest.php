<?php

use App\Models\DeviceAuthorization;
use App\Models\User;
use Laravel\Dusk\Browser;

/*
|--------------------------------------------------------------------------
| Login Page
|--------------------------------------------------------------------------
*/

describe('Login Page', function () {
    test('displays welcome message and GitHub sign-in button', function () {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->assertSee('Welcome to Chief')
                ->assertSee('Sign in with GitHub')
                ->assertPresent('[data-test="github-login-button"]');
        });
    });

    test('redirects unauthenticated user to login', function () {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard')
                ->assertPathIs('/login');
        });
    });

    test('shows documentation link', function () {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitForText('Welcome to Chief')
                ->assertSeeLink('Documentation');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Authenticated Dashboard / Onboarding
|--------------------------------------------------------------------------
*/

describe('Onboarding Flow', function () {
    test('new user with no devices sees onboarding', function () {
        $user = User::factory()->create(['email' => 'onboard@example.com']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Welcome to Chief')
                ->assertSee('Connect your machine')
                ->assertSee('chief login')
                ->assertSee('Deploy Server');
        });
    });

    test('user without email is redirected to email capture', function () {
        $user = User::factory()->create(['email' => null]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertPathIs('/email-capture');
        });
    });

    test('user with device sees dashboard with projects', function () {
        $user = User::factory()->create(['email' => 'dashboard@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
            'device_name' => 'test-server',
        ]);
        \App\Models\CachedProjectState::factory()->running()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'my-project',
            'project_name' => 'My Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertSee('Projects')
                ->assertSee('My Project');
        });
    });
});
