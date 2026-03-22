<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Settings\TeamSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class);

Route::middleware('auth')->group(function () {
    Route::get('/settings/team', [TeamSettingsController::class, 'index'])->name('settings.team');
    Route::put('/settings/team/name', [TeamSettingsController::class, 'updateName'])->name('settings.team.update-name');
    Route::delete('/settings/team/members', [TeamSettingsController::class, 'removeMember'])->name('settings.team.remove-member');
    Route::put('/settings/team/transfer', [TeamSettingsController::class, 'transferOwnership'])->name('settings.team.transfer');
});

require __DIR__.'/auth.php';
