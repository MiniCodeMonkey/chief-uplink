<?php

namespace App\Jobs;

use App\Enums\ServerStatus;
use App\Models\ManagedServer;
use App\Services\CloudProvider\CloudProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionServerJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public ManagedServer $server) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $credential = $this->server->credential;
        $sshKey = $this->server->sshKey;

        $provider = CloudProviderFactory::make($credential->provider, $credential->api_key);

        try {
            $result = $provider->createServer([
                'name' => $this->server->name,
                'size_id' => $this->server->size_id,
                'region_id' => $this->server->region_id,
                'ssh_key' => $sshKey->public_key,
            ]);

            $this->server->update([
                'provider_server_id' => $result['server_id'],
                'ip_address' => $result['ip_address'],
                'status' => ServerStatus::Active,
            ]);
        } catch (\Throwable) {
            $this->server->update([
                'status' => ServerStatus::Failed,
            ]);
        }
    }
}
