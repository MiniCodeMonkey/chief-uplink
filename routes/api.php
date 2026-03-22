<?php

use App\Http\Controllers\Api\DeviceAuthController;
use App\Http\Controllers\Api\DeviceCommandController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/device/request', [DeviceAuthController::class, 'request'])->name('api.device.request');
Route::post('/auth/device/verify', [DeviceAuthController::class, 'verify'])->name('api.device.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/devices/{device}/commands', [DeviceCommandController::class, 'store'])->name('api.devices.commands.store');
});
