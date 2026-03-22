<?php

namespace App\Services\CloudProvider;

use App\Contracts\CloudProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HetznerProvider implements CloudProviderInterface
{
    private const BASE_URL = 'https://api.hetzner.cloud/v1';

    private const DEBIAN_12_IMAGE = 'debian-12';

    public function __construct(private string $apiKey) {}

    /**
     * {@inheritDoc}
     */
    public function listSizes(): array
    {
        $response = $this->client()->get('/server_types');

        return collect($response->json('server_types', []))
            ->map(fn (array $type) => [
                'id' => (string) $type['id'],
                'name' => $type['name'],
                'vcpus' => $type['cores'],
                'memory_mb' => (int) ($type['memory'] * 1024),
                'disk_gb' => $type['disk'],
                'price_monthly' => (float) ($type['prices'][0]['price_monthly']['gross'] ?? 0),
            ])
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function listRegions(): array
    {
        $response = $this->client()->get('/locations');

        return collect($response->json('locations', []))
            ->map(fn (array $location) => [
                'id' => (string) $location['id'],
                'name' => $location['description'],
                'slug' => $location['name'],
                'available' => true,
            ])
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function createServer(array $config): array
    {
        $sshKeyResponse = $this->client()->post('/ssh_keys', [
            'name' => $config['name'].'-key',
            'public_key' => $config['ssh_key'],
        ]);

        $sshKeyId = $sshKeyResponse->json('ssh_key.id');

        $response = $this->client()->post('/servers', [
            'name' => $config['name'],
            'server_type' => $config['size_id'],
            'location' => $config['region_id'],
            'image' => self::DEBIAN_12_IMAGE,
            'ssh_keys' => [$sshKeyId],
        ]);

        $server = $response->json('server');

        return [
            'server_id' => (string) $server['id'],
            'ip_address' => $server['public_net']['ipv4']['ip'] ?? null,
            'status' => $this->normalizeStatus($server['status']),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getServer(string $serverId): array
    {
        $response = $this->client()->get("/servers/{$serverId}");

        $server = $response->json('server');

        return [
            'server_id' => (string) $server['id'],
            'name' => $server['name'],
            'ip_address' => $server['public_net']['ipv4']['ip'] ?? null,
            'status' => $this->normalizeStatus($server['status']),
            'size_id' => (string) $server['server_type']['id'],
            'region_id' => (string) $server['datacenter']['location']['id'],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function rebootServer(string $serverId): array
    {
        $this->client()->post("/servers/{$serverId}/actions/reboot");

        return ['success' => true];
    }

    /**
     * {@inheritDoc}
     */
    public function resizeServer(string $serverId, string $sizeId): array
    {
        $this->client()->post("/servers/{$serverId}/actions/change_type", [
            'server_type' => $sizeId,
            'upgrade_disk' => true,
        ]);

        return ['success' => true];
    }

    /**
     * {@inheritDoc}
     */
    public function rebuildServer(string $serverId): array
    {
        $this->client()->post("/servers/{$serverId}/actions/rebuild", [
            'image' => self::DEBIAN_12_IMAGE,
        ]);

        return ['success' => true];
    }

    /**
     * {@inheritDoc}
     */
    public function destroyServer(string $serverId): array
    {
        $this->client()->delete("/servers/{$serverId}");

        return ['success' => true];
    }

    /**
     * {@inheritDoc}
     */
    public function getMetrics(string $serverId): array
    {
        $now = now();
        $start = $now->copy()->subMinutes(5)->toIso8601String();
        $end = $now->toIso8601String();

        $response = $this->client()->get("/servers/{$serverId}/metrics", [
            'type' => 'cpu,disk,network',
            'start' => $start,
            'end' => $end,
        ]);

        $metrics = $response->json('metrics.time_series', []);

        return [
            'cpu_percent' => $this->extractLastValue($metrics, 'cpu'),
            'memory_percent' => 0.0,
            'disk_percent' => $this->extractLastValue($metrics, 'disk.0.bandwidth.read'),
            'network_in_bytes' => (int) $this->extractLastValue($metrics, 'network.0.bandwidth.in'),
            'network_out_bytes' => (int) $this->extractLastValue($metrics, 'network.0.bandwidth.out'),
        ];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->throw();
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'running' => 'active',
            'initializing' => 'provisioning',
            'starting' => 'provisioning',
            'stopping' => 'stopping',
            'off' => 'off',
            'deleting' => 'deleting',
            'rebuilding' => 'rebuilding',
            default => $status,
        };
    }

    /**
     * @param  array<string, mixed>  $timeSeries
     */
    private function extractLastValue(array $timeSeries, string $key): float
    {
        $values = $timeSeries[$key]['values'] ?? [];
        $last = end($values);

        return (float) ($last[1] ?? 0);
    }
}
