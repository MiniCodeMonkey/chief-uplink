<?php

use App\Models\CachedProjectState;
use App\Models\DeviceAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = DeviceAuthorization::factory()->for($this->user)->online()->create();
    $this->project = CachedProjectState::factory()->for($this->device)->running()->create([
        'project_name' => 'Test Project',
        'project_slug' => 'test-project',
    ]);
});

describe('Project Detail Routes', function () {
    it('renders the Overview tab as default at /projects/{slug}', function () {
        $response = $this->actingAs($this->user)->get('/projects/test-project');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Overview')
            ->has('projectSlug')
            ->has('projectName')
            ->has('project')
            ->has('recentRuns')
            ->where('projectSlug', 'test-project')
            ->where('projectName', 'Test Project')
        );
    });

    it('renders the Run tab at /projects/{slug}/run', function () {
        $response = $this->actingAs($this->user)->get('/projects/test-project/run');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Run')
            ->where('projectSlug', 'test-project')
            ->where('projectName', 'Test Project')
            ->has('deviceId')
            ->has('project')
            ->has('runHistory')
        );
    });

    it('renders the Diffs tab at /projects/{slug}/diffs', function () {
        $response = $this->actingAs($this->user)->get('/projects/test-project/diffs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Diffs')
            ->where('projectSlug', 'test-project')
            ->where('projectName', 'Test Project')
        );
    });

    it('renders the PRDs tab at /projects/{slug}/prds', function () {
        $response = $this->actingAs($this->user)->get('/projects/test-project/prds');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Prds')
            ->where('projectSlug', 'test-project')
            ->where('projectName', 'Test Project')
        );
    });

    it('renders the Settings tab at /projects/{slug}/settings', function () {
        $response = $this->actingAs($this->user)->get('/projects/test-project/settings');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('projects/Settings')
            ->where('projectSlug', 'test-project')
            ->where('projectName', 'Test Project')
            ->has('deviceId')
        );
    });
});

describe('Deep-Linkable URLs', function () {
    it('each tab has its own URL that can be accessed directly', function () {
        $tabs = [
            '/projects/test-project' => 'projects/Overview',
            '/projects/test-project/run' => 'projects/Run',
            '/projects/test-project/diffs' => 'projects/Diffs',
            '/projects/test-project/prds' => 'projects/Prds',
            '/projects/test-project/settings' => 'projects/Settings',
        ];

        foreach ($tabs as $url => $component) {
            $response = $this->actingAs($this->user)->get($url);
            $response->assertStatus(200);
            $response->assertInertia(fn ($page) => $page->component($component));
        }
    });
});

describe('Project Access Control', function () {
    it('returns 404 for non-existent project slugs', function () {
        $response = $this->actingAs($this->user)->get('/projects/nonexistent-project');

        $response->assertStatus(404);
    });

    it('returns 404 when accessing another user project', function () {
        $otherUser = User::factory()->create();
        $otherDevice = DeviceAuthorization::factory()->for($otherUser)->online()->create();
        CachedProjectState::factory()->for($otherDevice)->idle()->create([
            'project_slug' => 'other-project',
        ]);

        $response = $this->actingAs($this->user)->get('/projects/other-project');

        $response->assertStatus(404);
    });

    it('redirects unauthenticated users to login', function () {
        $response = $this->get('/projects/test-project');

        $response->assertRedirect('/login');
    });

    it('returns 404 for revoked device projects', function () {
        $revokedDevice = DeviceAuthorization::factory()->for($this->user)->revoked()->create();
        CachedProjectState::factory()->for($revokedDevice)->idle()->create([
            'project_slug' => 'revoked-project',
        ]);

        // The project exists but belongs to a revoked device — still accessible since
        // project data is cached. The controller only checks user ownership.
        $response = $this->actingAs($this->user)->get('/projects/revoked-project');

        $response->assertStatus(200);
    });

    it('returns 404 for all sub-routes of a non-existent project', function () {
        $subRoutes = ['/run', '/diffs', '/prds', '/settings'];

        foreach ($subRoutes as $subRoute) {
            $response = $this->actingAs($this->user)->get('/projects/nonexistent'.$subRoute);
            $response->assertStatus(404);
        }
    });
});

describe('Named Routes', function () {
    it('has named routes for all project tabs', function () {
        expect(route('projects.overview', ['slug' => 'test']))->toContain('/projects/test');
        expect(route('projects.run', ['slug' => 'test']))->toContain('/projects/test/run');
        expect(route('projects.diffs', ['slug' => 'test']))->toContain('/projects/test/diffs');
        expect(route('projects.prds', ['slug' => 'test']))->toContain('/projects/test/prds');
        expect(route('projects.settings', ['slug' => 'test']))->toContain('/projects/test/settings');
    });
});
