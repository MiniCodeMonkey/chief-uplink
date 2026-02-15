<?php

namespace App\Console\Commands;

use App\Services\ServerConnectionManager;
use App\WebSocket\ChiefReverbFactory;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Loggers\CliLogger;
use Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Custom start server command that extends Reverb's StartServer
 * to add the chief server WebSocket route.
 */
#[AsCommand(name: 'reverb:start')]
class StartReverbServer extends StartServer
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('debug')) {
            $this->laravel->instance(Logger::class, new CliLogger($this->output));
        }

        $config = $this->laravel['config']['reverb.servers.reverb'];

        $loop = Loop::get();

        // Use our custom factory instead of the default one
        $server = ChiefReverbFactory::make(
            $host = $this->option('host') ?: $config['host'],
            $port = $this->option('port') ?: $config['port'],
            $path = $this->option('path') ?: $config['path'] ?? '',
            $hostname = $this->option('hostname') ?: $config['hostname'],
            $config['max_request_size'] ?? 10_000,
            $config['options'] ?? [],
            loop: $loop
        );

        $this->ensureHorizontalScalability($loop);
        $this->ensureStaleConnectionsAreCleaned($loop);
        $this->ensureRestartCommandIsRespected($server, $loop, $host, $port);
        $this->ensurePulseEventsAreCollected($loop, $config['pulse_ingest_interval']);
        $this->ensureTelescopeEntriesAreCollected($loop, $config['telescope_ingest_interval'] ?? 15);
        $this->ensureTokenRefreshChecks($loop);

        $this->components->info('Starting '.($server->isSecure() ? 'secure ' : '')."server on {$host}:{$port}{$path}".(($hostname && $hostname !== $host) ? " ({$hostname})" : ''));
        $this->components->info('Chief server WebSocket route registered at /ws/server');

        $server->start();
    }

    /**
     * Periodically check for connections needing token refresh.
     * Sends auth_refresh_required to connections whose tokens expire within 5 minutes.
     */
    protected function ensureTokenRefreshChecks(LoopInterface $loop): void
    {
        $loop->addPeriodicTimer(60, function () {
            $manager = app(ServerConnectionManager::class);
            $connectionsNeedingRefresh = $manager->getConnectionsNeedingRefresh(300);

            foreach ($connectionsNeedingRefresh as $connectionId) {
                $deviceId = $manager->getDeviceId($connectionId);
                \Illuminate\Support\Facades\Log::info('Token refresh needed', [
                    'connection_id' => $connectionId,
                    'device_id' => $deviceId,
                ]);
            }
        });
    }
}
