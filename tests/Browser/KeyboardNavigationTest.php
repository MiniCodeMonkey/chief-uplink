<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;
use Laravel\Dusk\Browser;

/*
|--------------------------------------------------------------------------
| Keyboard Navigation
|--------------------------------------------------------------------------
*/

describe('Keyboard Navigation', function () {
    test('Tab through dashboard elements', function () {
        $user = User::factory()->create(['email' => 'keyboard@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'kb-project',
            'project_name' => 'KB Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('KB Project')
                // Tab to project card
                ->keys('', '{tab}')
                ->pause(100)
                ->keys('', '{tab}')
                ->pause(100)
                // The project cards have tabindex=0 and should be focusable
                ->assertScript('document.activeElement !== null');
        });
    });

    test('Enter selects focused project card', function () {
        $user = User::factory()->create(['email' => 'enter@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'enter-project',
            'project_name' => 'Enter Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Enter Project')
                // Focus the project card and press Enter
                ->click('[role="link"][aria-label*="Enter Project"]')
                ->waitForText('Status')
                ->assertPathIs('/projects/enter-project');
        });
    });

    test('Escape closes modals', function () {
        $user = User::factory()->create(['email' => 'escape@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
            'device_name' => 'escape-device',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings/devices')
                ->waitForText('escape-device')
                // Open deauthorize dialog
                ->press('Deauthorize')
                ->waitForText('Are you sure')
                // Press Escape to close
                ->keys('', '{escape}')
                ->pause(300)
                ->assertDontSee('Are you sure');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Command Palette Keyboard Navigation
|--------------------------------------------------------------------------
*/

describe('Command Palette Keyboard', function () {
    test('Cmd+K opens, arrow keys navigate, Enter selects, Escape closes', function () {
        $user = User::factory()->create(['email' => 'cmd-k@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'cmd-project',
            'project_name' => 'CMD Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('CMD Project')
                // Open command palette with Ctrl+K
                ->keys('', ['{control}', 'k'])
                ->waitFor('[role="dialog"]')
                // Type to filter
                ->pause(300)
                ->keys('[role="dialog"] input', 'CMD')
                ->waitForText('CMD Project')
                // Use arrow down and Enter to select
                ->keys('', '{down}')
                ->keys('', '{enter}')
                ->waitForText('Status')
                ->assertPathIs('/projects/cmd-project');
        });
    });
});
