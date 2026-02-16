<?php

namespace App\Providers;

use App\Services\PrdSessionManager;
use App\Services\ServerConnectionManager;
use Illuminate\Support\ServiceProvider;

class WebSocketServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ServerConnectionManager::class);
        $this->app->singleton(PrdSessionManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
