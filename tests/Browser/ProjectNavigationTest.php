<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\RunHistory;
use App\Models\User;
use Laravel\Dusk\Browser;

/*
|--------------------------------------------------------------------------
| Project Navigation
|--------------------------------------------------------------------------
*/

describe('Project Navigation', function () {
    test('navigates to project overview from dashboard', function () {
        $user = User::factory()->create(['email' => 'nav@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->running()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'nav-project',
            'project_name' => 'Nav Project',
            'current_prd_name' => 'v1.0 Features',
            'stories_completed' => 3,
            'stories_total' => 10,
            'git_branch' => 'main',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertSee('Nav Project')
                ->click('[role="link"][aria-label*="Nav Project"]')
                ->waitForText('Status')
                ->assertPathIs('/projects/nav-project')
                ->assertSee('v1.0 Features')
                ->assertSee('Story progress');
        });
    });

    test('switches between project tabs', function () {
        $user = User::factory()->create(['email' => 'tabs@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'tab-project',
            'project_name' => 'Tab Project',
        ]);
        RunHistory::factory()->completed()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'tab-project',
            'prd_name' => 'v1.0',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/projects/tab-project')
                ->waitForText('Status')
                // Switch to Run tab
                ->clickLink('Run')
                ->waitForText('Run History')
                ->assertPathIs('/projects/tab-project/run')
                ->assertSee('v1.0');
        });
    });

    test('project overview shows story list for running project', function () {
        $user = User::factory()->create(['email' => 'story@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->running()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'story-project',
            'project_name' => 'Story Project',
            'current_prd_name' => 'Feature PRD',
            'stories_completed' => 5,
            'stories_total' => 12,
            'story_details' => [
                ['id' => 'US-001', 'title' => 'User Login', 'status' => 'completed'],
                ['id' => 'US-002', 'title' => 'User Registration', 'status' => 'in_progress'],
                ['id' => 'US-003', 'title' => 'Password Reset', 'status' => 'pending'],
            ],
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/projects/story-project')
                ->waitForText('Status')
                ->assertSee('Feature PRD')
                ->assertSee('5/12');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Run Tab
|--------------------------------------------------------------------------
*/

describe('Run Tab', function () {
    test('shows run history with story results', function () {
        $user = User::factory()->create(['email' => 'run@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->idle()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'run-project',
            'project_name' => 'Run Project',
        ]);
        RunHistory::factory()->completed()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'run-project',
            'prd_name' => 'API Features',
            'stories_completed' => 8,
            'stories_total' => 8,
            'duration_seconds' => 1200,
        ]);
        RunHistory::factory()->failed()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'run-project',
            'prd_name' => 'UI Refresh',
            'stories_completed' => 3,
            'stories_total' => 5,
            'error_message' => 'Test failures in component tests',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/projects/run-project/run')
                ->waitForText('Run History')
                ->assertSee('API Features')
                ->assertSee('UI Refresh')
                ->assertSee('8/8 stories')
                ->assertSee('3/5 stories');
        });
    });

    test('shows empty state when no runs exist', function () {
        $user = User::factory()->create(['email' => 'empty-run@example.com']);
        $device = DeviceAuthorization::factory()->online()->create([
            'user_id' => $user->id,
        ]);
        CachedProjectState::factory()->noPrd()->create([
            'device_authorization_id' => $device->id,
            'project_slug' => 'empty-run-project',
            'project_name' => 'Empty Run Project',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/projects/empty-run-project/run')
                ->waitForText('No runs yet');
        });
    });
});
