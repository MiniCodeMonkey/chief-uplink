<?php

namespace App\WebSocket;

use App\Services\ServerConnectionManager;
use Laravel\Reverb\Servers\Reverb\Factory;
use Laravel\Reverb\Servers\Reverb\Http\Route;
use Symfony\Component\Routing\RouteCollection;

class ChiefReverbFactory extends Factory
{
    /**
     * Generate the routes required to handle Pusher requests,
     * plus the custom chief server WebSocket route.
     */
    protected static function pusherRoutes(string $path): RouteCollection
    {
        $routes = parent::pusherRoutes($path);

        // Add the chief server WebSocket route
        $routes->add(
            'chief_server',
            Route::get('/ws/server', new ChiefServerController(
                app(ServerConnectionManager::class)
            ))
        );

        return $routes;
    }
}
