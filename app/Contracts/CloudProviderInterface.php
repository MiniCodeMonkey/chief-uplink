<?php

namespace App\Contracts;

interface CloudProviderInterface
{
    /**
     * List available server sizes/types.
     *
     * @return array<int, array{id: string, name: string, vcpus: int, memory_mb: int, disk_gb: int, price_monthly: float}>
     */
    public function listSizes(): array;

    /**
     * List available regions/locations.
     *
     * @return array<int, array{id: string, name: string, slug: string, available: bool}>
     */
    public function listRegions(): array;

    /**
     * Create a server with the given configuration.
     *
     * @param  array{name: string, size_id: string, region_id: string, ssh_key: string}  $config
     * @return array{server_id: string, ip_address: string|null, status: string}
     */
    public function createServer(array $config): array;

    /**
     * Get server details by ID.
     *
     * @return array{server_id: string, name: string, ip_address: string|null, status: string, size_id: string, region_id: string}
     */
    public function getServer(string $serverId): array;

    /**
     * Reboot a server.
     *
     * @return array{success: bool}
     */
    public function rebootServer(string $serverId): array;

    /**
     * Resize a server to a new size.
     *
     * @return array{success: bool}
     */
    public function resizeServer(string $serverId, string $sizeId): array;

    /**
     * Rebuild a server with Debian 12.
     *
     * @return array{success: bool}
     */
    public function rebuildServer(string $serverId): array;

    /**
     * Destroy a server.
     *
     * @return array{success: bool}
     */
    public function destroyServer(string $serverId): array;

    /**
     * Get server metrics.
     *
     * @return array{cpu_percent: float, memory_percent: float, disk_percent: float, network_in_bytes: int, network_out_bytes: int}
     */
    public function getMetrics(string $serverId): array;
}
