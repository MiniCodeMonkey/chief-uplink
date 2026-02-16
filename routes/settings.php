<?php

use App\Http\Controllers\Settings\CloudDeployController;
use App\Http\Controllers\Settings\CloudProviderKeyController;
use App\Http\Controllers\Settings\DeviceController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\PushSubscriptionController;
use App\Http\Middleware\EnsureEmailProvided;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', EnsureEmailProvided::class])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])
        ->middleware('throttle:account-deletion')
        ->name('profile.destroy');

    Route::get('settings/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::delete('settings/devices/{id}', [DeviceController::class, 'destroy'])->name('devices.destroy');

    Route::get('settings/cloud-servers', [CloudProviderKeyController::class, 'index'])->name('cloud-servers.index');
    Route::post('settings/cloud-servers', [CloudProviderKeyController::class, 'store'])->name('cloud-servers.store');
    Route::delete('settings/cloud-servers/{id}', [CloudProviderKeyController::class, 'destroy'])->name('cloud-servers.destroy');

    // Cloud deploy wizard
    Route::get('settings/cloud-deploy', [CloudDeployController::class, 'create'])
        ->middleware('throttle:cloud-deploy')
        ->name('cloud-deploy.create');
    Route::post('settings/cloud-deploy/regions', [CloudDeployController::class, 'regions'])->name('cloud-deploy.regions');
    Route::post('settings/cloud-deploy/tiers', [CloudDeployController::class, 'tiers'])->name('cloud-deploy.tiers');
    Route::post('settings/cloud-deploy', [CloudDeployController::class, 'deploy'])
        ->middleware('throttle:cloud-deploy')
        ->name('cloud-deploy.deploy');
    Route::get('settings/cloud-deploy/{id}/status', [CloudDeployController::class, 'status'])->name('cloud-deploy.status');
    Route::post('settings/cloud-deploy/{id}/restart', [CloudDeployController::class, 'restartChief'])->name('cloud-deploy.restart');
    Route::delete('settings/cloud-deploy/{id}', [CloudDeployController::class, 'destroy'])->name('cloud-deploy.destroy');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/preferences', function () {
        return Inertia::render('settings/Preferences');
    })->name('preferences.edit');

    // Push notification subscriptions
    Route::post('settings/push-subscription', [PushSubscriptionController::class, 'store'])->name('push-subscription.store');
    Route::delete('settings/push-subscription', [PushSubscriptionController::class, 'destroy'])->name('push-subscription.destroy');

    // Notification preferences
    Route::patch('settings/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
});
