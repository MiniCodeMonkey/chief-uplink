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
