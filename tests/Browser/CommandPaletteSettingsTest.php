<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;
use Laravel\Dusk\Browser;

/*
|--------------------------------------------------------------------------
| Command Palette
|--------------------------------------------------------------------------
*/

describe('Command Palette', function () {
    test('opens with Cmd+K and searches projects', function () {
        $user = User::factory()->create(['email' => 'palette@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
            'device_name' => 'main-server',
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'search-project',
            'project_name' => 'Search Project',
        ]);
        CachedProjectState::factory()->running()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'other-project',
            'project_name' => 'Other Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Projects')
                ->keys('', ['{control}', 'k'])
                ->waitFor('[role="dialog"]')
                ->assertSee('Search')
                ->type('input[placeholder*="search" i], input[type="text"]', 'Search')
                ->waitForText('Search Project')
                ->assertSee('Search Project')
                // Close with Escape
                ->keys('', '{escape}')
                ->waitUntilMissing('[role="dialog"]', 3);
        });
    });

    test('navigates to project via command palette', function () {
        $user = User::factory()->create(['email' => 'palette-nav@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'palette-target',
            'project_name' => 'Palette Target',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Projects')
                ->keys('', ['{control}', 'k'])
                ->waitFor('[role="dialog"]')
                ->type('input[placeholder*="search" i], input[type="text"]', 'Palette')
                ->waitForText('Palette Target')
                ->keys('', '{enter}')
                ->waitForText('Status')
                ->assertPathIs('/projects/palette-target');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Settings - Devices
|--------------------------------------------------------------------------
*/

describe('Settings Devices', function () {
    test('shows device list with status', function () {
        $user = User::factory()->create(['email' => 'devices@example.com']);
        DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
            'device_name' => 'production-server',
            'os' => 'linux',
            'arch' => 'amd64',
            'chief_version' => '0.5.3',
        ]);
        DeviceAuthorization::factory()->offline()->create([
            'user_id' => $user->id,
            'device_name' => 'dev-laptop',
            'os' => 'darwin',
            'arch' => 'arm64',
            'chief_version' => '0.5.1',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings/devices')
                ->waitForText('Devices')
                ->assertSee('production-server')
                ->assertSee('dev-laptop')
                ->assertSee('Linux')
                ->assertSee('macOS')
                ->assertSee('Deauthorize');
        });
    });

    test('deauthorizes a device with confirmation dialog', function () {
        $user = User::factory()->create(['email' => 'deauth@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
            'device_name' => 'deauth-target',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings/devices')
                ->waitForText('deauth-target')
                ->press('Deauthorize')
                ->waitFor('[role="dialog"]')
                ->assertSee('Are you sure')
                // Click the confirm button within the dialog
                ->within('[role="dialog"]', function (Browser $dialog) {
                    $dialog->press('Deauthorize');
                })
                ->waitUntilMissing('[role="dialog"]', 5);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Settings - Appearance
|--------------------------------------------------------------------------
*/

describe('Settings Appearance', function () {
    test('dark/light mode toggle persists across page navigation', function () {
        $user = User::factory()->create(['email' => 'theme@example.com']);
        DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings/appearance')
                ->waitForText('Appearance settings')
                // Click dark mode button using XPath
                ->clickAtXPath('//button[.//span[text()="Dark"]]')
                ->pause(500)
                ->assertScript('document.documentElement.classList.contains("dark")')
                // Navigate away and back
                ->visit('/dashboard')
                ->pause(500)
                ->assertScript('document.documentElement.classList.contains("dark")')
                // Switch back to light
                ->visit('/settings/appearance')
                ->waitForText('Appearance settings')
                ->clickAtXPath('//button[.//span[text()="Light"]]')
                ->pause(500)
                ->assertScript('!document.documentElement.classList.contains("dark")');
        });
    });
});
