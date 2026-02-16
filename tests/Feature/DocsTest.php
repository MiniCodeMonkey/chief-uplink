<?php

use function Pest\Laravel\get;

describe('Documentation', function () {
    it('shows the docs index page', function () {
        get('/docs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('docs/Show')
                ->has('slug')
                ->has('content')
                ->has('sections')
                ->where('slug', 'getting-started')
            );
    });

    it('shows a specific doc page', function () {
        get('/docs/prds')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('docs/Show')
                ->where('slug', 'prds')
                ->has('content')
                ->has('sections')
            );
    });

    it('returns 404 for non-existent docs', function () {
        get('/docs/non-existent-page')
            ->assertNotFound();
    });

    it('is accessible without authentication', function () {
        get('/docs')
            ->assertOk();

        get('/docs/getting-started')
            ->assertOk();
    });

    it('provides all documentation sections', function () {
        get('/docs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sections', 8)
                ->where('sections.0.slug', 'getting-started')
                ->where('sections.0.title', 'Getting Started')
            );
    });

    it('provides markdown content for each section', function () {
        $slugs = ['getting-started', 'prds', 'runs', 'diffs', 'remote-monitoring', 'configuration', 'cloud-deployment', 'self-hosting'];

        foreach ($slugs as $slug) {
            get("/docs/{$slug}")
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('slug', $slug)
                    ->where('content', fn ($content) => strlen($content) > 0)
                );
        }
    });
});
