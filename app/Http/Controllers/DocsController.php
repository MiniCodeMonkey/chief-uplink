<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class DocsController extends Controller
{
    /**
     * Documentation sections in display order.
     *
     * @var array<int, array{slug: string, title: string}>
     */
    private const SECTIONS = [
        ['slug' => 'getting-started', 'title' => 'Getting Started'],
        ['slug' => 'prds', 'title' => 'PRDs'],
        ['slug' => 'runs', 'title' => 'Runs'],
        ['slug' => 'diffs', 'title' => 'Viewing Diffs'],
        ['slug' => 'remote-monitoring', 'title' => 'Remote Monitoring'],
        ['slug' => 'configuration', 'title' => 'Configuration'],
        ['slug' => 'cloud-deployment', 'title' => 'Cloud Deployment'],
        ['slug' => 'self-hosting', 'title' => 'Self-Hosting'],
    ];

    public function index(): Response
    {
        return $this->show('getting-started');
    }

    public function show(string $slug): Response
    {
        $path = base_path("docs/{$slug}.md");

        if (! File::exists($path)) {
            abort(404);
        }

        $content = File::get($path);

        return Inertia::render('docs/Show', [
            'slug' => $slug,
            'content' => $content,
            'sections' => self::SECTIONS,
        ]);
    }
}
