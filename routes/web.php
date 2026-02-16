<?php

use App\Http\Controllers\Api\CommandRelayController;
use App\Http\Controllers\Api\MessageBufferController;
use App\Http\Controllers\Auth\DeviceCodeEntryController;
use App\Http\Controllers\Auth\EmailCaptureController;
use App\Http\Controllers\Auth\GitHubAuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Middleware\EnsureEmailProvided;
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

// Email capture routes (authenticated but before EnsureEmailProvided)
Route::middleware(['auth'])->group(function () {
    Route::get('email-capture', [EmailCaptureController::class, 'show'])->name('email-capture.show');
    Route::post('email-capture', [EmailCaptureController::class, 'store'])->name('email-capture.store');
    Route::post('email-capture/skip', [EmailCaptureController::class, 'skip'])->name('email-capture.skip');
});

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', EnsureEmailProvided::class])->name('dashboard');

Route::middleware(['auth', EnsureEmailProvided::class])->group(function () {
    // Device code entry routes
    Route::get('/oauth/device', [DeviceCodeEntryController::class, 'show'])->name('oauth.device');
    Route::post('/oauth/device/verify', [DeviceCodeEntryController::class, 'verify'])
        ->middleware('throttle:device-code-entry')
        ->name('oauth.device.verify');
    Route::get('/oauth/device/confirm/{code}', [DeviceCodeEntryController::class, 'confirm'])->name('oauth.device.confirm');
    Route::post('/oauth/device/authorize', [DeviceCodeEntryController::class, 'authorize'])->name('oauth.device.authorize');
    Route::post('/oauth/device/deny', [DeviceCodeEntryController::class, 'deny'])->name('oauth.device.deny');

    Route::get('/projects/{slug}', [ProjectController::class, 'overview'])->name('projects.overview');
    Route::get('/projects/{slug}/run', [ProjectController::class, 'run'])->name('projects.run');
    Route::get('/projects/{slug}/diffs', [ProjectController::class, 'diffs'])->name('projects.diffs');
    Route::get('/projects/{slug}/prds', [ProjectController::class, 'prds'])->name('projects.prds');
    Route::get('/projects/{slug}/settings', [ProjectController::class, 'settings'])->name('projects.settings');
    Route::get('/projects/{slug}/prd/new', [ProjectController::class, 'prdCreate'])->name('projects.prd.create');
    Route::get('/projects/{slug}/prd/{prdId}/refine', [ProjectController::class, 'prdRefine'])->name('projects.prd.refine');

    // WebSocket message buffer replay (for browser reconnection)
    Route::post('/ws/buffer/replay', [MessageBufferController::class, 'replay'])->name('ws.buffer.replay');

    // WebSocket command relay (browser → chief via WebSocket)
    Route::post('/ws/command/{deviceId}', [CommandRelayController::class, 'send'])
        ->middleware('throttle:browser-commands')
        ->name('ws.command.send');
});

require __DIR__.'/settings.php';

// Dev-only component playground
if (app()->environment('local')) {
    Route::get('/dev/components', function () {
        return Inertia::render('dev/Components');
    })->name('dev.components');
}
