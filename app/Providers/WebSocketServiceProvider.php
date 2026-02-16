<?php

namespace App\Providers;

use App\Console\Commands\StartReverbServer;
use App\Services\PrdSessionManager;
use App\Services\ServerConnectionManager;
use Illuminate\Support\ServiceProvider;
use Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer;

class WebSocketServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ServerConnectionManager::class);
        $this->app->singleton(PrdSessionManager::class);

        // Bind Reverb's StartServer to our custom command so that when
        // ReverbServerProvider::boot() calls resolveCommands([StartServer::class]),
        // the container resolves our StartReverbServer instead.
        $this->app->bind(StartServer::class, StartReverbServer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
