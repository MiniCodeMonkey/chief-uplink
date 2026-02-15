<?php

use App\Models\CachedProjectState;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/projects/{slug}', function (string $slug) {
        $project = CachedProjectState::where('project_slug', $slug)->firstOrFail();

        return Inertia::render('projects/Overview', [
            'projectSlug' => $project->project_slug,
            'projectName' => $project->project_name,
        ]);
    })->name('projects.overview');

    Route::get('/projects/{slug}/run', function (string $slug) {
        $project = CachedProjectState::where('project_slug', $slug)->firstOrFail();

        return Inertia::render('projects/Run', [
            'projectSlug' => $project->project_slug,
            'projectName' => $project->project_name,
        ]);
    })->name('projects.run');

    Route::get('/projects/{slug}/diffs', function (string $slug) {
        $project = CachedProjectState::where('project_slug', $slug)->firstOrFail();

        return Inertia::render('projects/Diffs', [
            'projectSlug' => $project->project_slug,
            'projectName' => $project->project_name,
        ]);
    })->name('projects.diffs');

    Route::get('/projects/{slug}/prds', function (string $slug) {
        $project = CachedProjectState::where('project_slug', $slug)->firstOrFail();

        return Inertia::render('projects/Prds', [
            'projectSlug' => $project->project_slug,
            'projectName' => $project->project_name,
        ]);
    })->name('projects.prds');
});

require __DIR__.'/settings.php';

// Dev-only component playground
if (app()->environment('local')) {
    Route::get('/dev/components', function () {
        return Inertia::render('dev/Components');
    })->name('dev.components');
}
