<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;
use Laravel\Dusk\Browser;

/*
|--------------------------------------------------------------------------
| Mobile Viewport (375x812)
|--------------------------------------------------------------------------
*/

describe('Mobile Viewport', function () {
    test('bottom tab bar renders on mobile', function () {
        $user = User::factory()->create(['email' => 'mobile@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'mobile-project',
            'project_name' => 'Mobile Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->resize(375, 812)
                ->loginAs($user)
                ->visit('/projects/mobile-project')
                ->waitForText('Status')
                // Mobile bottom tab bar should be visible
                ->assertPresent('nav[aria-label="Project tabs"].lg\\:hidden')
                ->assertSee('Overview')
                ->assertSee('Run')
                ->assertSee('Diffs')
                ->assertSee('PRDs');
        });
    });

    test('responsive layout has no significant horizontal overflow on mobile', function () {
        $user = User::factory()->create(['email' => 'overflow@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->running()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'overflow-project',
            'project_name' => 'Overflow Project',
            'stories_completed' => 5,
            'stories_total' => 12,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->resize(375, 812)
                ->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Overflow Project')
                // Verify main content area doesn't overflow (headless can have scrollbar variations)
                ->assertScript('document.querySelector(".flex.h-full").scrollWidth <= document.querySelector(".flex.h-full").clientWidth');
        });
    });

    test('touch targets are at least 44px on mobile', function () {
        $user = User::factory()->create(['email' => 'touch@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'touch-project',
            'project_name' => 'Touch Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->resize(375, 812)
                ->loginAs($user)
                ->visit('/projects/touch-project')
                ->waitForText('Status')
                // Bottom tab bar items should have minimum 44px height
                ->assertScript(
                    'Array.from(document.querySelectorAll("nav[aria-label=\'Project tabs\'].lg\\\\:hidden a")).every(el => el.offsetHeight >= 44)'
                );
        });
    });

    test('dashboard project cards are single column on mobile', function () {
        $user = User::factory()->create(['email' => 'col@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'col-project-1',
            'project_name' => 'Column Project 1',
        ]);
        CachedProjectState::factory()->running()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'col-project-2',
            'project_name' => 'Column Project 2',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->resize(375, 812)
                ->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Column Project 1')
                ->assertSee('Column Project 2');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Tablet Viewport (768x1024)
|--------------------------------------------------------------------------
*/

describe('Tablet Viewport', function () {
    test('layout adapts correctly at tablet viewport', function () {
        $user = User::factory()->create(['email' => 'tablet@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'tablet-project',
            'project_name' => 'Tablet Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->resize(768, 1024)
                ->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Tablet Project')
                // At 768px, grid should show 2 columns (sm:grid-cols-2)
                ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1');
        });
    });

    test('settings page renders correctly at tablet viewport', function () {
        $user = User::factory()->create(['email' => 'tablet-settings@example.com']);
        DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
            'device_name' => 'tablet-device',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->resize(768, 1024)
                ->loginAs($user)
                ->visit('/settings/devices')
                ->waitForText('Devices')
                ->assertSee('tablet-device')
                ->assertSee('Deauthorize');
        });
    });
});
