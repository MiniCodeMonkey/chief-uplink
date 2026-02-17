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

describe('Tab Navigation', function () {
    test('navigating through all project tabs renders expected content', function () {
        $user = User::where('email', 'e2e-test@example.com')->firstOrFail();

        $this->browse(function (Browser $browser) use ($user) {
            // Start on the Overview tab
            $browser->loginAs($user)
                ->visit('/projects/test-project')
                ->waitFor('nav[aria-label="Project tabs"]', 15)
                ->assertUrlIs(url('/projects/test-project'))
                // Overview shows card headings (project has a seeded PRD, so not no_prd state)
                ->assertSee('Status')
                ->assertSee('Recent Activity');

            // Navigate to Run tab via tab bar
            $browser->within('nav[aria-label="Project tabs"]', function (Browser $nav) {
                    $nav->clickLink('Run');
                })
                ->waitForLocation('/projects/test-project/run')
                ->assertUrlIs(url('/projects/test-project/run'))
                ->assertSee('Start Run');

            // Navigate to Diffs tab
            $browser->within('nav[aria-label="Project tabs"]', function (Browser $nav) {
                    $nav->clickLink('Diffs');
                })
                ->waitForLocation('/projects/test-project/diffs')
                ->assertUrlIs(url('/projects/test-project/diffs'))
                ->assertSee('Diffs');

            // Navigate to PRDs tab
            $browser->within('nav[aria-label="Project tabs"]', function (Browser $nav) {
                    $nav->clickLink('PRDs');
                })
                ->waitForLocation('/projects/test-project/prds')
                ->assertUrlIs(url('/projects/test-project/prds'));

            // Navigate to Settings tab
            $browser->within('nav[aria-label="Project tabs"]', function (Browser $nav) {
                    $nav->clickLink('Settings');
                })
                ->waitForLocation('/projects/test-project/settings')
                ->assertUrlIs(url('/projects/test-project/settings'));
        });
    });
});

describe('PRDs', function () {
    test('prd listing shows PRDs from CLI workspace', function () {
        $user = User::where('email', 'e2e-test@example.com')->firstOrFail();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/projects/test-project/prds')
                // Wait for skeleton loaders to disappear (prds_response received)
                ->waitUntilMissing('[data-slot="skeleton"]', 30)
                // Assert the feature-auth PRD appears
                ->assertSee('Feature Auth')
                // Assert story count is displayed (3 stories from seeded prd.json)
                ->assertSee('3 stories');
        });
    });
});

describe('PRD Chat', function () {
    test('create new PRD via Claude chat', function () {
        $user = User::where('email', 'e2e-test@example.com')->firstOrFail();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/projects/test-project/prd/new')
                // Wait for the chat input to be ready
                ->waitFor('textarea[aria-label="Chat message input"]', 15)
                // Type a project description
                ->type('textarea[aria-label="Chat message input"]', 'Create a simple todo list app with add and delete functionality')
                // Click Send button (desktop button)
                ->press('Send')
                // Wait for Claude's response to appear (up to 120s for real Claude API call)
                // The .prose-chat class appears when Claude's streamed text is rendered
                ->waitFor('.prose-chat', 120)
                // Assert the thinking indicator dots are gone
                ->assertMissing('.animate-pulse.rounded-full.bg-muted-foreground')
                // Assert chat content is visible (response text rendered)
                ->assertPresent('.prose-chat');
        });
    })->timeout(180); // 3-minute overall timeout for Claude API latency
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
