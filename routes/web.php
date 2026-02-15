<?php

use App\Http\Controllers\Auth\GitHubAuthController;
use App\Models\CachedProjectState;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

// GitHub OAuth routes
Route::middleware('guest')->group(function () {
    Route::get('login', [GitHubAuthController::class, 'login'])->name('login');
    Route::get('auth/github', [GitHubAuthController::class, 'redirect'])->name('auth.github');
    Route::get('auth/github/callback', [GitHubAuthController::class, 'callback'])->name('auth.github.callback');
});

Route::post('logout', [GitHubAuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
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
