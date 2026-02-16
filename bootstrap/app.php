<?php

use App\Http\Middleware\AddSecurityHeaders;
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
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'auth']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            AddSecurityHeaders::class,
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

        // Browser WebSocket commands: 60 per user per minute
        RateLimiter::for('browser-commands', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Login attempts: 5 per IP per minute
        RateLimiter::for('login-attempts', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // General API endpoints: 60 per user per minute
        RateLimiter::for('general-api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Clone/create project: 10 per user per hour
        RateLimiter::for('clone-create-project', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->id ?: $request->ip());
        });

        // Cloud deploy: 5 per user per hour
        RateLimiter::for('cloud-deploy', function (Request $request) {
            return Limit::perHour(5)->by($request->user()?->id ?: $request->ip());
        });

        // Account deletion: 1 per user per hour
        RateLimiter::for('account-deletion', function (Request $request) {
            return Limit::perHour(1)->by($request->user()?->id ?: $request->ip());
        });

        // Push notifications: 20 per user per hour (enforced in SendPushNotification job)
        RateLimiter::for('push-notifications', function (Request $request) {
            return Limit::perHour(20)->by($request->user()?->id ?: $request->ip());
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // Only render Inertia error pages for web requests with standard HTTP errors
            if (! app()->environment(['local', 'testing'])
                && in_array($status, [403, 404, 500, 503])
                && ! $request->is('api/*')
            ) {
                return Inertia::render('Error', [
                    'status' => $status,
                ])->toResponse($request)->setStatusCode($status);
            }

            // In local/testing environment, render Inertia error for 404 only (let others show debug page)
            if (app()->environment(['local', 'testing'])
                && $status === 404
                && ! $request->is('api/*')
            ) {
                return Inertia::render('Error', [
                    'status' => $status,
                ])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
