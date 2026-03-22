<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GitHubKeyController;
use App\Http\Controllers\PrdController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\Settings\CloudProviderCredentialController;
use App\Http\Controllers\Settings\ProfileSettingsController;
use App\Http\Controllers\Settings\SshKeyController;
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
    Route::get('/runs/{run}/live', [RunController::class, 'live'])->name('runs.live');
    Route::get('/runs/{run}/diffs', [RunController::class, 'diffs'])->name('runs.diffs');
    Route::get('/files/{device}/{path?}', [FileController::class, 'show'])->where('path', '.*')->name('files.show');
    Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
    Route::get('/servers/create', [ServerController::class, 'create'])->name('servers.create');
    Route::post('/servers', [ServerController::class, 'store'])->name('servers.store');
    Route::get('/servers/credentials/{credential}/regions', [ServerController::class, 'regions'])->name('servers.regions');
    Route::get('/servers/credentials/{credential}/sizes', [ServerController::class, 'sizes'])->name('servers.sizes');
    Route::get('/servers/{server}', [ServerController::class, 'show'])->name('servers.show');
    Route::post('/servers/{server}/deploy-key', [GitHubKeyController::class, 'store'])->name('servers.deploy-key');
    Route::get('/github/keys/authorize', [GitHubKeyController::class, 'authorize'])->name('github.keys.authorize');
    Route::get('/github/keys/callback', [GitHubKeyController::class, 'callback'])->name('github.keys.callback');
    Route::get('/settings', [ProfileSettingsController::class, 'index'])->name('settings.profile');
    Route::put('/settings/profile', [ProfileSettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('/settings/theme', [ProfileSettingsController::class, 'updateTheme'])->name('settings.theme.update');
    Route::get('/settings/team', [TeamSettingsController::class, 'index'])->name('settings.team');
    Route::put('/settings/team/name', [TeamSettingsController::class, 'updateName'])->name('settings.team.update-name');
    Route::post('/settings/team/invite', [TeamSettingsController::class, 'invite'])->name('settings.team.invite');
    Route::delete('/settings/team/members', [TeamSettingsController::class, 'removeMember'])->name('settings.team.remove-member');
    Route::put('/settings/team/transfer', [TeamSettingsController::class, 'transferOwnership'])->name('settings.team.transfer');
    Route::get('/settings/credentials', [CloudProviderCredentialController::class, 'index'])->name('settings.credentials');
    Route::post('/settings/credentials', [CloudProviderCredentialController::class, 'store'])->name('settings.credentials.store');
    Route::put('/settings/credentials/{credential}', [CloudProviderCredentialController::class, 'update'])->name('settings.credentials.update');
    Route::delete('/settings/credentials/{credential}', [CloudProviderCredentialController::class, 'destroy'])->name('settings.credentials.destroy');
    Route::post('/settings/ssh-keys', [SshKeyController::class, 'store'])->name('settings.ssh-keys.store');
    Route::put('/settings/ssh-keys/{sshKey}', [SshKeyController::class, 'update'])->name('settings.ssh-keys.update');
    Route::delete('/settings/ssh-keys/{sshKey}', [SshKeyController::class, 'destroy'])->name('settings.ssh-keys.destroy');
});

require __DIR__.'/auth.php';
