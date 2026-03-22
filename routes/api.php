<?php

use App\Http\Controllers\Api\DeviceAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/device/request', [DeviceAuthController::class, 'request'])->name('api.device.request');
Route::post('/auth/device/verify', [DeviceAuthController::class, 'verify'])->name('api.device.verify');
