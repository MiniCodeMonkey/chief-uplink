<?php

use App\Http\Controllers\Auth\GitHubController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/auth/github', [GitHubController::class, 'redirect'])->name('github.redirect');
    Route::get('/auth/github/callback', [GitHubController::class, 'callback'])->name('github.callback');
});

Route::post('/logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');
