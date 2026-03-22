<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\PrdController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\Settings\ProfileSettingsController;
use App\Http\Controllers\Settings\TeamSettingsController;
use App\Http\Controllers\TeamSwitcherController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class);

Route::middleware('auth')->group(function () {
    Route::put('/team/switch', TeamSwitcherController::class)->name('team.switch');
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/prds/{prd}', [PrdController::class, 'show'])->name('prds.show');
    Route::get('/prds/{prd}/chat', [PrdController::class, 'chat'])->name('prds.chat');
    Route::get('/runs/{run}', [RunController::class, 'show'])->name('runs.show');
    Route::get('/settings', [ProfileSettingsController::class, 'index'])->name('settings.profile');
    Route::put('/settings/profile', [ProfileSettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('/settings/theme', [ProfileSettingsController::class, 'updateTheme'])->name('settings.theme.update');
    Route::get('/settings/team', [TeamSettingsController::class, 'index'])->name('settings.team');
    Route::put('/settings/team/name', [TeamSettingsController::class, 'updateName'])->name('settings.team.update-name');
    Route::delete('/settings/team/members', [TeamSettingsController::class, 'removeMember'])->name('settings.team.remove-member');
    Route::put('/settings/team/transfer', [TeamSettingsController::class, 'transferOwnership'])->name('settings.team.transfer');
});

require __DIR__.'/auth.php';
