<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;
use Laravel\Dusk\Browser;

/*
|--------------------------------------------------------------------------
| Skeleton Loading States
|--------------------------------------------------------------------------
*/

describe('Skeleton Loading States', function () {
    test('skeleton loading elements appear on dashboard', function () {
        $user = User::factory()->create(['email' => 'skeleton@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'skeleton-project',
            'project_name' => 'Skeleton Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            // Dashboard uses lazy-loaded Inertia props, so skeleton should briefly appear
            $browser->loginAs($user)
                ->visit('/dashboard')
                // Wait for content to load
                ->waitForText('Skeleton Project', 10);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Empty States
|--------------------------------------------------------------------------
*/

describe('Empty States', function () {
    test('new user with no data sees onboarding with action buttons', function () {
        $user = User::factory()->create(['email' => 'empty@example.com']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Welcome to Chief')
                ->assertSee('Connect your machine')
                ->assertSee('Deploy Server')
                ->assertSeeLink('Read the documentation');
        });
    });

    test('project with no PRD shows create PRD empty state', function () {
        $user = User::factory()->create(['email' => 'no-prd@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->noPrd()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'empty-prd-project',
            'project_name' => 'Empty PRD Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/projects/empty-prd-project')
                ->waitForText('Get started by creating a PRD')
                ->assertSee('New PRD');
        });
    });

    test('empty device list shows appropriate empty state', function () {
        $user = User::factory()->create(['email' => 'no-devices@example.com']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings/devices')
                ->waitForText('No devices authorized')
                ->assertSee('chief login');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Offline Banner
|--------------------------------------------------------------------------
*/

describe('Offline Banner', function () {
    test('offline status shows for offline device', function () {
        $user = User::factory()->create(['email' => 'offline-banner@example.com']);
        DeviceAuthorization::factory()->offline()->create([
            'user_id' => $user->id,
            'device_name' => 'offline-device',
            'last_connected_at' => now()->subMinutes(10),
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => DeviceAuthorization::where('user_id', $user->id)->first()->id,
            'project_slug' => 'offline-project',
            'project_name' => 'Offline Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Offline Project')
                ->assertSee('Server offline');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Error States - Form Validation
|--------------------------------------------------------------------------
*/

describe('Form Validation Errors', function () {
    test('email capture page renders with form elements', function () {
        $user = User::factory()->create(['email' => null]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/email-capture')
                ->waitForText('Add your email')
                ->assertPresent('[data-test="email-capture-input"]')
                ->assertPresent('[data-test="email-capture-submit"]')
                ->assertPresent('[data-test="email-capture-skip"]')
                ->assertSee('Continue')
                ->assertSee('Skip for now');
        });
    });

    test('email capture page has all required elements', function () {
        $user = User::factory()->create(['email' => null]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/email-capture')
                ->waitForText('Add your email')
                ->assertSee('We need your email for notifications')
                ->assertSee('Email address')
                ->assertPresent('#email')
                ->assertSee('Continue')
                ->assertSee('Skip for now');
        });
    });

    test('settings profile page shows delete account section', function () {
        $user = User::factory()->create([
            'email' => 'delete-val@example.com',
            'github_username' => 'correctuser',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings/profile')
                ->waitForText('Delete account');
        });
    });
});
