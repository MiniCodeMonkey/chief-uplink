<?php

use App\Contracts\CloudProviderInterface;
use App\Enums\CloudProvider;
use App\Services\CloudProvider\CloudProviderFactory;
use App\Services\CloudProvider\DigitalOceanProvider;
use App\Services\CloudProvider\HetznerProvider;
use Illuminate\Support\Facades\Http;

// ── Factory ─────────────────────────────────────────────────────────

it('creates a HetznerProvider for hetzner', function () {
    $provider = CloudProviderFactory::make(CloudProvider::Hetzner, 'test-api-key');

    expect($provider)->toBeInstanceOf(HetznerProvider::class)
        ->and($provider)->toBeInstanceOf(CloudProviderInterface::class);
});

it('creates a DigitalOceanProvider for digitalocean', function () {
    $provider = CloudProviderFactory::make(CloudProvider::DigitalOcean, 'test-api-key');

    expect($provider)->toBeInstanceOf(DigitalOceanProvider::class)
        ->and($provider)->toBeInstanceOf(CloudProviderInterface::class);
});

// ── Hetzner: listSizes ──────────────────────────────────────────────

it('lists sizes from hetzner with normalized format', function () {
    Http::fake([
        'api.hetzner.cloud/v1/server_types' => Http::response([
            'server_types' => [
                [
                    'id' => 1,
                    'name' => 'cx22',
                    'cores' => 2,
                    'memory' => 4,
                    'disk' => 40,
                    'prices' => [
                        ['price_monthly' => ['gross' => '4.51']],
                    ],
                ],
            ],
        ]),
    ]);

    $provider = new HetznerProvider('test-key');
    $sizes = $provider->listSizes();

    expect($sizes)->toHaveCount(1)
        ->and($sizes[0])->toBe([
            'id' => '1',
            'name' => 'cx22',
            'vcpus' => 2,
            'memory_mb' => 4096,
            'disk_gb' => 40,
            'price_monthly' => 4.51,
        ]);
});

// ── Hetzner: listRegions ────────────────────────────────────────────

it('lists regions from hetzner with normalized format', function () {
    Http::fake([
        'api.hetzner.cloud/v1/locations' => Http::response([
            'locations' => [
                [
                    'id' => 1,
                    'name' => 'fsn1',
                    'description' => 'Falkenstein DC Park 1',
                ],
            ],
        ]),
    ]);

    $provider = new HetznerProvider('test-key');
    $regions = $provider->listRegions();

    expect($regions)->toHaveCount(1)
        ->and($regions[0])->toBe([
            'id' => '1',
            'name' => 'Falkenstein DC Park 1',
            'slug' => 'fsn1',
            'available' => true,
        ]);
});

// ── Hetzner: createServer ───────────────────────────────────────────

it('creates a server on hetzner with ssh key and debian 12', function () {
    Http::fake([
        'api.hetzner.cloud/v1/ssh_keys' => Http::response([
            'ssh_key' => ['id' => 42],
        ]),
        'api.hetzner.cloud/v1/servers' => Http::response([
            'server' => [
                'id' => 123,
                'status' => 'initializing',
                'public_net' => [
                    'ipv4' => ['ip' => '1.2.3.4'],
                ],
            ],
        ]),
    ]);

    $provider = new HetznerProvider('test-key');
    $result = $provider->createServer([
        'name' => 'my-server',
        'size_id' => 'cx22',
        'region_id' => 'fsn1',
        'ssh_key' => 'ssh-ed25519 AAAA...',
    ]);

    expect($result)->toBe([
        'server_id' => '123',
        'ip_address' => '1.2.3.4',
        'status' => 'provisioning',
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/ssh_keys')
            && $request['public_key'] === 'ssh-ed25519 AAAA...';
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/servers')
            && $request['image'] === 'debian-12'
            && $request['ssh_keys'] === [42];
    });
});

// ── Hetzner: getServer ──────────────────────────────────────────────

it('gets a server from hetzner with normalized format', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers/123' => Http::response([
            'server' => [
                'id' => 123,
                'name' => 'my-server',
                'status' => 'running',
                'public_net' => [
                    'ipv4' => ['ip' => '1.2.3.4'],
                ],
                'server_type' => ['id' => 1],
                'datacenter' => ['location' => ['id' => 2]],
            ],
        ]),
    ]);

    $provider = new HetznerProvider('test-key');
    $result = $provider->getServer('123');

    expect($result)->toBe([
        'server_id' => '123',
        'name' => 'my-server',
        'ip_address' => '1.2.3.4',
        'status' => 'active',
        'size_id' => '1',
        'region_id' => '2',
    ]);
});

// ── Hetzner: rebootServer ───────────────────────────────────────────

it('reboots a server on hetzner', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers/123/actions/reboot' => Http::response(['action' => ['id' => 1]]),
    ]);

    $provider = new HetznerProvider('test-key');
    $result = $provider->rebootServer('123');

    expect($result)->toBe(['success' => true]);
});

// ── Hetzner: resizeServer ───────────────────────────────────────────

it('resizes a server on hetzner', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers/123/actions/change_type' => Http::response(['action' => ['id' => 1]]),
    ]);

    $provider = new HetznerProvider('test-key');
    $result = $provider->resizeServer('123', 'cx32');

    expect($result)->toBe(['success' => true]);

    Http::assertSent(function ($request) {
        return $request['server_type'] === 'cx32'
            && $request['upgrade_disk'] === true;
    });
});

// ── Hetzner: rebuildServer ──────────────────────────────────────────

it('rebuilds a server on hetzner with debian 12', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers/123/actions/rebuild' => Http::response(['action' => ['id' => 1]]),
    ]);

    $provider = new HetznerProvider('test-key');
    $result = $provider->rebuildServer('123');

    expect($result)->toBe(['success' => true]);

    Http::assertSent(function ($request) {
        return $request['image'] === 'debian-12';
    });
});

// ── Hetzner: destroyServer ──────────────────────────────────────────

it('destroys a server on hetzner', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers/123' => Http::response(null, 204),
    ]);

    $provider = new HetznerProvider('test-key');
    $result = $provider->destroyServer('123');

    expect($result)->toBe(['success' => true]);
});

// ── Hetzner: getMetrics ─────────────────────────────────────────────

it('gets metrics from hetzner', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers/123/metrics*' => Http::response([
            'metrics' => [
                'time_series' => [
                    'cpu' => ['values' => [['2026-01-01', '45.5']]],
                    'network.0.bandwidth.in' => ['values' => [['2026-01-01', '1024']]],
                    'network.0.bandwidth.out' => ['values' => [['2026-01-01', '2048']]],
                ],
            ],
        ]),
    ]);

    $provider = new HetznerProvider('test-key');
    $result = $provider->getMetrics('123');

    expect($result)->toBe([
        'cpu_percent' => 45.5,
        'memory_percent' => 0.0,
        'disk_percent' => 0.0,
        'network_in_bytes' => 1024,
        'network_out_bytes' => 2048,
    ]);
});

// ── DigitalOcean: listSizes ─────────────────────────────────────────

it('lists sizes from digitalocean with normalized format', function () {
    Http::fake([
        'api.digitalocean.com/v2/sizes' => Http::response([
            'sizes' => [
                [
                    'slug' => 's-1vcpu-1gb',
                    'vcpus' => 1,
                    'memory' => 1024,
                    'disk' => 25,
                    'price_monthly' => 6.0,
                ],
            ],
        ]),
    ]);

    $provider = new DigitalOceanProvider('test-key');
    $sizes = $provider->listSizes();

    expect($sizes)->toHaveCount(1)
        ->and($sizes[0])->toBe([
            'id' => 's-1vcpu-1gb',
            'name' => 's-1vcpu-1gb',
            'vcpus' => 1,
            'memory_mb' => 1024,
            'disk_gb' => 25,
            'price_monthly' => 6.0,
        ]);
});

// ── DigitalOcean: listRegions ───────────────────────────────────────

it('lists regions from digitalocean with normalized format', function () {
    Http::fake([
        'api.digitalocean.com/v2/regions' => Http::response([
            'regions' => [
                [
                    'slug' => 'nyc1',
                    'name' => 'New York 1',
                    'available' => true,
                ],
            ],
        ]),
    ]);

    $provider = new DigitalOceanProvider('test-key');
    $regions = $provider->listRegions();

    expect($regions)->toHaveCount(1)
        ->and($regions[0])->toBe([
            'id' => 'nyc1',
            'name' => 'New York 1',
            'slug' => 'nyc1',
            'available' => true,
        ]);
});

// ── DigitalOcean: createServer ──────────────────────────────────────

it('creates a server on digitalocean with ssh key and debian 12', function () {
    Http::fake([
        'api.digitalocean.com/v2/account/keys' => Http::response([
            'ssh_key' => ['id' => 99],
        ]),
        'api.digitalocean.com/v2/droplets' => Http::response([
            'droplet' => [
                'id' => 456,
                'status' => 'new',
                'networks' => [
                    'v4' => [
                        ['type' => 'public', 'ip_address' => '5.6.7.8'],
                    ],
                ],
            ],
        ]),
    ]);

    $provider = new DigitalOceanProvider('test-key');
    $result = $provider->createServer([
        'name' => 'do-server',
        'size_id' => 's-1vcpu-1gb',
        'region_id' => 'nyc1',
        'ssh_key' => 'ssh-ed25519 BBBB...',
    ]);

    expect($result)->toBe([
        'server_id' => '456',
        'ip_address' => '5.6.7.8',
        'status' => 'provisioning',
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/account/keys')
            && $request['public_key'] === 'ssh-ed25519 BBBB...';
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/droplets')
            && $request['image'] === 'debian-12-x64'
            && $request['ssh_keys'] === [99];
    });
});

// ── DigitalOcean: getServer ─────────────────────────────────────────

it('gets a server from digitalocean with normalized format', function () {
    Http::fake([
        'api.digitalocean.com/v2/droplets/456' => Http::response([
            'droplet' => [
                'id' => 456,
                'name' => 'do-server',
                'status' => 'active',
                'networks' => [
                    'v4' => [
                        ['type' => 'public', 'ip_address' => '5.6.7.8'],
                    ],
                ],
                'size' => ['slug' => 's-1vcpu-1gb'],
                'region' => ['slug' => 'nyc1'],
            ],
        ]),
    ]);

    $provider = new DigitalOceanProvider('test-key');
    $result = $provider->getServer('456');

    expect($result)->toBe([
        'server_id' => '456',
        'name' => 'do-server',
        'ip_address' => '5.6.7.8',
        'status' => 'active',
        'size_id' => 's-1vcpu-1gb',
        'region_id' => 'nyc1',
    ]);
});

// ── DigitalOcean: rebootServer ──────────────────────────────────────

it('reboots a server on digitalocean', function () {
    Http::fake([
        'api.digitalocean.com/v2/droplets/456/actions' => Http::response(['action' => ['id' => 1]]),
    ]);

    $provider = new DigitalOceanProvider('test-key');
    $result = $provider->rebootServer('456');

    expect($result)->toBe(['success' => true]);

    Http::assertSent(function ($request) {
        return $request['type'] === 'reboot';
    });
});

// ── DigitalOcean: resizeServer ──────────────────────────────────────

it('resizes a server on digitalocean', function () {
    Http::fake([
        'api.digitalocean.com/v2/droplets/456/actions' => Http::response(['action' => ['id' => 1]]),
    ]);

    $provider = new DigitalOceanProvider('test-key');
    $result = $provider->resizeServer('456', 's-2vcpu-2gb');

    expect($result)->toBe(['success' => true]);

    Http::assertSent(function ($request) {
        return $request['type'] === 'resize'
            && $request['size'] === 's-2vcpu-2gb';
    });
});

// ── DigitalOcean: rebuildServer ─────────────────────────────────────

it('rebuilds a server on digitalocean with debian 12', function () {
    Http::fake([
        'api.digitalocean.com/v2/droplets/456/actions' => Http::response(['action' => ['id' => 1]]),
    ]);

    $provider = new DigitalOceanProvider('test-key');
    $result = $provider->rebuildServer('456');

    expect($result)->toBe(['success' => true]);

    Http::assertSent(function ($request) {
        return $request['type'] === 'rebuild'
            && $request['image'] === 'debian-12-x64';
    });
});

// ── DigitalOcean: destroyServer ─────────────────────────────────────

it('destroys a server on digitalocean', function () {
    Http::fake([
        'api.digitalocean.com/v2/droplets/456' => Http::response(null, 204),
    ]);

    $provider = new DigitalOceanProvider('test-key');
    $result = $provider->destroyServer('456');

    expect($result)->toBe(['success' => true]);
});

// ── DigitalOcean: getMetrics ────────────────────────────────────────

it('gets metrics from digitalocean', function () {
    Http::fake([
        'api.digitalocean.com/v2/monitoring/metrics/droplet/cpu*' => Http::response([
            'data' => [
                'result' => [
                    ['values' => [['2026-01-01', '30.0']]],
                ],
            ],
        ]),
        'api.digitalocean.com/v2/monitoring/metrics/droplet/bandwidth*' => Http::response([
            'data' => [
                'result' => [
                    ['values' => [['2026-01-01', '512']]],
                ],
            ],
        ]),
    ]);

    $provider = new DigitalOceanProvider('test-key');
    $result = $provider->getMetrics('456');

    expect($result)
        ->toHaveKeys(['cpu_percent', 'memory_percent', 'disk_percent', 'network_in_bytes', 'network_out_bytes'])
        ->and($result['cpu_percent'])->toBe(30.0);
});

// ── Interface contract ──────────────────────────────────────────────

it('ensures both providers implement CloudProviderInterface', function () {
    expect(HetznerProvider::class)->toImplement(CloudProviderInterface::class)
        ->and(DigitalOceanProvider::class)->toImplement(CloudProviderInterface::class);
});
