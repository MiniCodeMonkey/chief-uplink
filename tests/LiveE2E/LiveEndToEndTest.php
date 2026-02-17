<?php

use App\Models\User;
use Laravel\Dusk\Browser;

/*
|--------------------------------------------------------------------------
| Live End-to-End Tests
|--------------------------------------------------------------------------
|
| These tests run against a fully live environment: real CLI process,
| real Reverb WebSocket server, real Laravel app, and a real browser.
| They are executed via tests/LiveE2E/run.sh which handles all
| infrastructure setup and teardown.
|
| Unlike the tests in tests/Browser/ which create mock data via
| factories, these tests rely on data seeded by `php artisan e2e:setup`
| and state pushed by the real chief CLI via WebSocket.
|
*/

describe('Dashboard', function () {
    test('project appears on dashboard from CLI state snapshot', function () {
        $user = User::where('email', 'e2e-test@example.com')->firstOrFail();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('test-project', 30)
                ->assertSee('test-project');
        });
    });
});

describe('Settings', function () {
    test('settings page loads config values from CLI', function () {
        $user = User::where('email', 'e2e-test@example.com')->firstOrFail();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/projects/test-project/settings')
                // Wait for skeleton loaders to disappear (settings_response received)
                ->waitUntilMissing('[data-slot="skeleton"]', 30)
                // Assert max iterations field shows 5 (from seeded config.yaml)
                ->assertInputValue('#max-iterations', '5')
                // Assert auto commit toggle is visible and ON (aria-checked="true")
                ->assertVisible('#auto-commit')
                ->assertAttribute('#auto-commit', 'aria-checked', 'true');
        });
    });

    test('settings update roundtrip persists changes to CLI config', function () {
        $user = User::where('email', 'e2e-test@example.com')->firstOrFail();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/projects/test-project/settings')
                // Wait for settings to load
                ->waitUntilMissing('[data-slot="skeleton"]', 30)
                // Confirm baseline value
                ->assertInputValue('#max-iterations', '5')
                // Change max iterations from 5 to 10
                ->type('#max-iterations', '10')
                // Click Save Settings
                ->press('Save Settings')
                // Wait for "Settings saved" toast (settings_updated via WebSocket)
                ->waitForText('Settings saved', 15)
                // Assert field shows 10
                ->assertInputValue('#max-iterations', '10')
                // Reload page to verify CLI persisted the change to disk
                ->visit('/projects/test-project/settings')
                ->waitUntilMissing('[data-slot="skeleton"]', 30)
                ->assertInputValue('#max-iterations', '10');
        });
    });
});
