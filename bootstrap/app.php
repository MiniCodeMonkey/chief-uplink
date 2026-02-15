<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ValidateDeviceAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'device.auth' => ValidateDeviceAccessToken::class,
        ]);
    })
    ->booted(function () {
        // Device code requests: 10 per IP per 15 minutes
        RateLimiter::for('device-code', function (Request $request) {
            return Limit::perMinutes(15, 10)->by($request->ip());
        });

        // Token refresh: 30 per device per hour
        RateLimiter::for('token-refresh', function (Request $request) {
            return Limit::perHour(30)->by($request->ip());
        });

        // Device code entry: 10 attempts per user per 15 minutes
        RateLimiter::for('device-code-entry', function (Request $request) {
            return Limit::perMinutes(15, 10)->by($request->user()?->id ?: $request->ip());
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
