<?php

namespace App\Services\CloudProvider;

use App\Contracts\CloudProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DigitalOceanProvider implements CloudProviderInterface
{
    private const BASE_URL = 'https://api.digitalocean.com/v2';

    private const DEBIAN_12_IMAGE = 'debian-12-x64';

    public function __construct(private string $apiKey) {}

    /**
     * {@inheritDoc}
     */
    public function listSizes(): array
    {
        $response = $this->client()->get('/sizes');

        return collect($response->json('sizes', []))
            ->map(fn (array $size) => [
                'id' => $size['slug'],
                'name' => $size['slug'],
                'vcpus' => $size['vcpus'],
                'memory_mb' => $size['memory'],
                'disk_gb' => $size['disk'],
                'price_monthly' => (float) $size['price_monthly'],
            ])
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function listRegions(): array
    {
        $response = $this->client()->get('/regions');

        return collect($response->json('regions', []))
            ->map(fn (array $region) => [
                'id' => $region['slug'],
                'name' => $region['name'],
                'slug' => $region['slug'],
                'available' => $region['available'],
            ])
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function createServer(array $config): array
    {
        $sshKeyResponse = $this->client()->post('/account/keys', [
            'name' => $config['name'].'-key',
            'public_key' => $config['ssh_key'],
        ]);

        $sshKeyId = $sshKeyResponse->json('ssh_key.id');

        $response = $this->client()->post('/droplets', [
            'name' => $config['name'],
            'size' => $config['size_id'],
            'region' => $config['region_id'],
            'image' => self::DEBIAN_12_IMAGE,
            'ssh_keys' => [$sshKeyId],
        ]);

        $droplet = $response->json('droplet');

        $ipAddress = collect($droplet['networks']['v4'] ?? [])
            ->firstWhere('type', 'public');

        return [
            'server_id' => (string) $droplet['id'],
            'ip_address' => $ipAddress['ip_address'] ?? null,
            'status' => $this->normalizeStatus($droplet['status']),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getServer(string $serverId): array
    {
        $response = $this->client()->get("/droplets/{$serverId}");

        $droplet = $response->json('droplet');

        $ipAddress = collect($droplet['networks']['v4'] ?? [])
            ->firstWhere('type', 'public');

        return [
            'server_id' => (string) $droplet['id'],
            'name' => $droplet['name'],
            'ip_address' => $ipAddress['ip_address'] ?? null,
            'status' => $this->normalizeStatus($droplet['status']),
            'size_id' => $droplet['size']['slug'],
            'region_id' => $droplet['region']['slug'],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function rebootServer(string $serverId): array
    {
        $this->client()->post("/droplets/{$serverId}/actions", [
            'type' => 'reboot',
        ]);

        return ['success' => true];
    }

    /**
     * {@inheritDoc}
     */
    public function resizeServer(string $serverId, string $sizeId): array
    {
        $this->client()->post("/droplets/{$serverId}/actions", [
            'type' => 'resize',
            'size' => $sizeId,
            'disk' => true,
        ]);

        return ['success' => true];
    }

    /**
     * {@inheritDoc}
     */
    public function rebuildServer(string $serverId): array
    {
        $this->client()->post("/droplets/{$serverId}/actions", [
            'type' => 'rebuild',
            'image' => self::DEBIAN_12_IMAGE,
        ]);

        return ['success' => true];
    }

    /**
     * {@inheritDoc}
     */
    public function destroyServer(string $serverId): array
    {
        $this->client()->delete("/droplets/{$serverId}");

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

        $response = $this->client()->get('/monitoring/metrics/droplet/cpu', [
            'host_id' => $serverId,
            'start' => $start,
            'end' => $end,
        ]);

        $cpuData = $response->json('data.result', []);
        $cpuPercent = 0.0;
        if (! empty($cpuData)) {
            $lastValues = collect($cpuData)->pluck('values')->flatten(1);
            $lastValue = $lastValues->last();
            $cpuPercent = (float) ($lastValue[1] ?? 0);
        }

        $bandwidthResponse = $this->client()->get('/monitoring/metrics/droplet/bandwidth', [
            'host_id' => $serverId,
            'start' => $start,
            'end' => $end,
            'interface' => 'public',
            'direction' => 'inbound',
        ]);

        $inboundData = $bandwidthResponse->json('data.result', []);
        $networkIn = 0;
        if (! empty($inboundData)) {
            $lastValue = collect($inboundData[0]['values'] ?? [])->last();
            $networkIn = (int) ($lastValue[1] ?? 0);
        }

        $outboundResponse = $this->client()->get('/monitoring/metrics/droplet/bandwidth', [
            'host_id' => $serverId,
            'start' => $start,
            'end' => $end,
            'interface' => 'public',
            'direction' => 'outbound',
        ]);

        $outboundData = $outboundResponse->json('data.result', []);
        $networkOut = 0;
        if (! empty($outboundData)) {
            $lastValue = collect($outboundData[0]['values'] ?? [])->last();
            $networkOut = (int) ($lastValue[1] ?? 0);
        }

        return [
            'cpu_percent' => $cpuPercent,
            'memory_percent' => 0.0,
            'disk_percent' => 0.0,
            'network_in_bytes' => $networkIn,
            'network_out_bytes' => $networkOut,
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
            'active' => 'active',
            'new' => 'provisioning',
            'off' => 'off',
            'archive' => 'off',
            default => $status,
        };
    }
}
