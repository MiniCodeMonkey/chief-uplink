<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CloudDeployment;
use App\Models\ProviderApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CloudDeployController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['hetzner', 'digitalocean'];

    public function create(Request $request): Response
    {
        $providerKeys = $request->user()
            ->providerApiKeys()
            ->get()
            ->map(fn (ProviderApiKey $key) => [
                'id' => $key->id,
                'provider' => $key->provider,
                'masked_key' => $key->masked_key,
                'account_name' => $key->account_name,
            ]);

        return Inertia::render('settings/CloudDeploy', [
            'providerKeys' => $providerKeys,
            'supportedProviders' => self::SUPPORTED_PROVIDERS,
        ]);
    }

    public function regions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(self::SUPPORTED_PROVIDERS)],
        ]);

        $apiKey = $this->getApiKey($request, $validated['provider']);

        if (! $apiKey) {
            return response()->json(['error' => 'No API key configured for this provider.'], 422);
        }

        try {
            $regions = match ($validated['provider']) {
                'hetzner' => $this->fetchHetznerRegions($apiKey),
                'digitalocean' => $this->fetchDigitalOceanRegions($apiKey),
                default => [],
            };

            return response()->json(['regions' => $regions]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch regions. Check your API key and try again.'], 500);
        }
    }

    public function tiers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(self::SUPPORTED_PROVIDERS)],
            'region' => ['required', 'string'],
        ]);

        $apiKey = $this->getApiKey($request, $validated['provider']);

        if (! $apiKey) {
            return response()->json(['error' => 'No API key configured for this provider.'], 422);
        }

        try {
            $tiers = match ($validated['provider']) {
                'hetzner' => $this->fetchHetznerTiers($apiKey),
                'digitalocean' => $this->fetchDigitalOceanTiers($apiKey, $validated['region']),
                default => [],
            };

            return response()->json(['tiers' => $tiers]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch server tiers. Check your API key and try again.'], 500);
        }
    }

    public function deploy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(self::SUPPORTED_PROVIDERS)],
            'region' => ['required', 'string'],
            'tier' => ['required', 'string'],
            'monthly_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $apiKey = $this->getApiKey($request, $validated['provider']);

        if (! $apiKey) {
            return response()->json(['error' => 'No API key configured for this provider.'], 422);
        }

        // Generate one-time setup token (10-minute expiry)
        $setupToken = Str::random(64);

        // Create the cloud deployment record
        $deployment = CloudDeployment::create([
            'user_id' => $request->user()->id,
            'provider' => $validated['provider'],
            'provider_api_key' => $apiKey,
            'region' => $validated['region'],
            'tier' => $validated['tier'],
            'monthly_cost' => $validated['monthly_cost'],
            'status' => 'provisioning',
            'setup_token' => $setupToken,
            'setup_token_expires_at' => now()->addMinutes(10),
        ]);

        try {
            $result = match ($validated['provider']) {
                'hetzner' => $this->createHetznerServer($apiKey, $deployment, $setupToken),
                'digitalocean' => $this->createDigitalOceanServer($apiKey, $deployment, $setupToken),
                default => throw new \RuntimeException('Unsupported provider'),
            };

            $deployment->update([
                'provider_server_id' => $result['server_id'],
                'ip_address' => $result['ip_address'] ?? null,
                'status' => 'provisioning',
            ]);

            return response()->json([
                'deployment_id' => $deployment->id,
                'provider_server_id' => $result['server_id'],
                'ip_address' => $result['ip_address'] ?? null,
                'status' => 'provisioning',
            ]);
        } catch (\Exception $e) {
            $deployment->update(['status' => 'destroyed']);

            return response()->json([
                'error' => $e->getMessage() ?: 'Failed to create server. Please try again.',
            ], 500);
        }
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $deployment = $request->user()
            ->cloudDeployments()
            ->findOrFail($id);

        // Check provider API for current status
        if ($deployment->status === 'provisioning' && $deployment->provider_server_id) {
            try {
                $serverStatus = match ($deployment->provider) {
                    'hetzner' => $this->checkHetznerStatus($deployment),
                    'digitalocean' => $this->checkDigitalOceanStatus($deployment),
                    default => null,
                };

                if ($serverStatus) {
                    $deployment->update([
                        'ip_address' => $serverStatus['ip_address'] ?? $deployment->ip_address,
                        'status' => $serverStatus['status'],
                    ]);
                }
            } catch (\Exception) {
                // Silently ignore status check failures
            }
        }

        return response()->json([
            'id' => $deployment->id,
            'status' => $deployment->status,
            'ip_address' => $deployment->ip_address,
            'provider' => $deployment->provider,
            'region' => $deployment->region,
            'tier' => $deployment->tier,
            'monthly_cost' => $deployment->monthly_cost,
        ]);
    }

    public function restartChief(Request $request, int $id): JsonResponse
    {
        $deployment = $request->user()
            ->cloudDeployments()
            ->where('status', 'active')
            ->findOrFail($id);

        if (! $deployment->ip_address || ! $deployment->provider_api_key) {
            return response()->json(['error' => 'Server is not ready for restart.'], 422);
        }

        try {
            $result = match ($deployment->provider) {
                'hetzner' => $this->restartHetznerChief($deployment),
                'digitalocean' => $this->restartDigitalOceanChief($deployment),
                default => throw new \RuntimeException('Unsupported provider'),
            };

            return response()->json(['success' => true, 'message' => 'Chief restart initiated.']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage() ?: 'Failed to restart Chief. Please try again.',
            ], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $deployment = $request->user()
            ->cloudDeployments()
            ->whereIn('status', ['active', 'provisioning', 'suspended'])
            ->findOrFail($id);

        try {
            if ($deployment->provider_server_id && $deployment->provider_api_key) {
                match ($deployment->provider) {
                    'hetzner' => $this->destroyHetznerServer($deployment),
                    'digitalocean' => $this->destroyDigitalOceanServer($deployment),
                    default => null,
                };
            }

            $deployment->update(['status' => 'destroyed']);

            return response()->json(['success' => true, 'message' => 'Server destroyed.']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage() ?: 'Failed to destroy server. Please try again.',
            ], 500);
        }
    }

    private function getApiKey(Request $request, string $provider): ?string
    {
        $key = $request->user()
            ->providerApiKeys()
            ->where('provider', $provider)
            ->first();

        return $key?->api_key;
    }

    private function generateCloudInitScript(string $setupToken): string
    {
        $appUrl = config('app.url');

        return <<<CLOUD_INIT
#!/bin/bash
set -e

# Install Chief CLI
curl -fsSL https://raw.githubusercontent.com/MiniCodeMonkey/chief/refs/heads/main/install.sh | bash

# Create chief user if not exists
id -u chief &>/dev/null || useradd -m -s /bin/bash chief

# Exchange setup token for credentials
RESPONSE=\$(curl -s -X POST "{$appUrl}/api/oauth/device/exchange" \\
  -H "Content-Type: application/json" \\
  -d '{"setup_token": "{$setupToken}"}')

ACCESS_TOKEN=\$(echo "\$RESPONSE" | jq -r '.access_token')
REFRESH_TOKEN=\$(echo "\$RESPONSE" | jq -r '.refresh_token')

if [ "\$ACCESS_TOKEN" = "null" ] || [ -z "\$ACCESS_TOKEN" ]; then
  echo "Failed to exchange setup token" >&2
  exit 1
fi

# Write credentials
mkdir -p /home/chief/.chief
cat > /home/chief/.chief/credentials.yaml << EOF
access_token: \$ACCESS_TOKEN
refresh_token: \$REFRESH_TOKEN
server_url: {$appUrl}
EOF

chown -R chief:chief /home/chief/.chief
chmod 600 /home/chief/.chief/credentials.yaml

# Start chief serve as a systemd service
cat > /etc/systemd/system/chief.service << EOF
[Unit]
Description=Chief Server
After=network.target

[Service]
Type=simple
User=chief
ExecStart=/usr/local/bin/chief serve
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable chief
systemctl start chief
CLOUD_INIT;
    }

    // --- Hetzner API Methods ---

    private function fetchHetznerRegions(string $apiKey): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(10)
            ->get('https://api.hetzner.cloud/v1/locations');

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to fetch Hetzner locations');
        }

        return collect($response->json('locations', []))
            ->map(fn ($location) => [
                'id' => $location['name'],
                'name' => $location['city'].', '.$location['country'],
                'description' => $location['description'],
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function fetchHetznerTiers(string $apiKey): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(10)
            ->get('https://api.hetzner.cloud/v1/server_types', ['per_page' => 50]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to fetch Hetzner server types');
        }

        $recommended = 'cx22';

        return collect($response->json('server_types', []))
            ->filter(fn ($type) => str_starts_with($type['name'], 'cx') || str_starts_with($type['name'], 'cpx'))
            ->map(fn ($type) => [
                'id' => $type['name'],
                'name' => strtoupper($type['name']),
                'cpu' => $type['cores'],
                'ram' => $type['memory'].' GB',
                'ram_mb' => (int) ($type['memory'] * 1024),
                'disk' => $type['disk'].' GB',
                'disk_gb' => $type['disk'],
                'monthly_cost' => $this->hetznerMonthlyPrice($type),
                'recommended' => $type['name'] === $recommended,
            ])
            ->sortBy('monthly_cost')
            ->values()
            ->all();
    }

    private function hetznerMonthlyPrice(array $type): float
    {
        // Hetzner prices are in the pricing object
        $prices = $type['prices'] ?? [];
        foreach ($prices as $price) {
            if (isset($price['price_monthly']['gross'])) {
                return (float) $price['price_monthly']['gross'];
            }
        }

        // Fallback estimates based on tier
        return match ($type['name']) {
            'cx22' => 5.49,
            'cx32' => 9.49,
            'cx42' => 17.49,
            'cpx21' => 7.49,
            'cpx31' => 12.99,
            'cpx41' => 22.99,
            default => 0.00,
        };
    }

    private function createHetznerServer(string $apiKey, CloudDeployment $deployment, string $setupToken): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.hetzner.cloud/v1/servers', [
                'name' => 'chief-'.Str::random(6),
                'server_type' => $deployment->tier,
                'location' => $deployment->region,
                'image' => 'ubuntu-24.04',
                'user_data' => $this->generateCloudInitScript($setupToken),
                'start_after_create' => true,
            ]);

        if (! $response->successful()) {
            $error = $response->json('error.message', 'Failed to create Hetzner server');
            throw new \RuntimeException($error);
        }

        $server = $response->json('server');

        return [
            'server_id' => (string) $server['id'],
            'ip_address' => $server['public_net']['ipv4']['ip'] ?? null,
        ];
    }

    private function checkHetznerStatus(CloudDeployment $deployment): ?array
    {
        $response = Http::withToken($deployment->provider_api_key)
            ->timeout(10)
            ->get("https://api.hetzner.cloud/v1/servers/{$deployment->provider_server_id}");

        if (! $response->successful()) {
            return null;
        }

        $server = $response->json('server');
        $status = match ($server['status'] ?? '') {
            'running' => 'active',
            'initializing', 'starting' => 'provisioning',
            'off', 'stopping' => 'suspended',
            'deleting' => 'destroyed',
            default => 'provisioning',
        };

        return [
            'status' => $status,
            'ip_address' => $server['public_net']['ipv4']['ip'] ?? null,
        ];
    }

    // --- DigitalOcean API Methods ---

    private function fetchDigitalOceanRegions(string $apiKey): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(10)
            ->get('https://api.digitalocean.com/v2/regions', ['per_page' => 50]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to fetch DigitalOcean regions');
        }

        return collect($response->json('regions', []))
            ->filter(fn ($region) => $region['available'])
            ->map(fn ($region) => [
                'id' => $region['slug'],
                'name' => $region['name'],
                'description' => $region['slug'],
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function fetchDigitalOceanTiers(string $apiKey, string $region): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(10)
            ->get('https://api.digitalocean.com/v2/sizes', ['per_page' => 100]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to fetch DigitalOcean sizes');
        }

        $recommended = 's-2vcpu-4gb';

        return collect($response->json('sizes', []))
            ->filter(fn ($size) => $size['available'] && in_array($region, $size['regions'] ?? []))
            ->filter(fn ($size) => str_starts_with($size['slug'], 's-'))
            ->map(fn ($size) => [
                'id' => $size['slug'],
                'name' => strtoupper(str_replace('-', ' ', $size['slug'])),
                'cpu' => $size['vcpus'],
                'ram' => ($size['memory'] / 1024).' GB',
                'ram_mb' => $size['memory'],
                'disk' => $size['disk'].' GB',
                'disk_gb' => $size['disk'],
                'monthly_cost' => $size['price_monthly'],
                'recommended' => $size['slug'] === $recommended,
            ])
            ->sortBy('monthly_cost')
            ->values()
            ->all();
    }

    private function createDigitalOceanServer(string $apiKey, CloudDeployment $deployment, string $setupToken): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.digitalocean.com/v2/droplets', [
                'name' => 'chief-'.Str::random(6),
                'region' => $deployment->region,
                'size' => $deployment->tier,
                'image' => 'ubuntu-24-04-x64',
                'user_data' => $this->generateCloudInitScript($setupToken),
            ]);

        if (! $response->successful()) {
            $error = $response->json('message', 'Failed to create DigitalOcean droplet');
            throw new \RuntimeException($error);
        }

        $droplet = $response->json('droplet');

        // DigitalOcean doesn't return IP immediately — it's assigned asynchronously
        $ip = null;
        $networks = $droplet['networks']['v4'] ?? [];
        foreach ($networks as $network) {
            if ($network['type'] === 'public') {
                $ip = $network['ip_address'];
                break;
            }
        }

        return [
            'server_id' => (string) $droplet['id'],
            'ip_address' => $ip,
        ];
    }

    private function checkDigitalOceanStatus(CloudDeployment $deployment): ?array
    {
        $response = Http::withToken($deployment->provider_api_key)
            ->timeout(10)
            ->get("https://api.digitalocean.com/v2/droplets/{$deployment->provider_server_id}");

        if (! $response->successful()) {
            return null;
        }

        $droplet = $response->json('droplet');
        $status = match ($droplet['status'] ?? '') {
            'active' => 'active',
            'new' => 'provisioning',
            'off', 'archive' => 'suspended',
            default => 'provisioning',
        };

        $ip = null;
        $networks = $droplet['networks']['v4'] ?? [];
        foreach ($networks as $network) {
            if ($network['type'] === 'public') {
                $ip = $network['ip_address'];
                break;
            }
        }

        return [
            'status' => $status,
            'ip_address' => $ip,
        ];
    }

    // --- Restart Chief Methods ---

    private function restartHetznerChief(CloudDeployment $deployment): void
    {
        // Hetzner soft reboot triggers systemd restart of chief service
        $response = Http::withToken($deployment->provider_api_key)
            ->timeout(15)
            ->post("https://api.hetzner.cloud/v1/servers/{$deployment->provider_server_id}/actions/reboot");

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to restart server via Hetzner API.');
        }
    }

    private function restartDigitalOceanChief(CloudDeployment $deployment): void
    {
        // DigitalOcean reboot via power cycle
        $response = Http::withToken($deployment->provider_api_key)
            ->timeout(15)
            ->post("https://api.digitalocean.com/v2/droplets/{$deployment->provider_server_id}/actions", [
                'type' => 'reboot',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to restart server via DigitalOcean API.');
        }
    }

    // --- Destroy Server Methods ---

    private function destroyHetznerServer(CloudDeployment $deployment): void
    {
        $response = Http::withToken($deployment->provider_api_key)
            ->timeout(15)
            ->delete("https://api.hetzner.cloud/v1/servers/{$deployment->provider_server_id}");

        if (! $response->successful() && $response->status() !== 404) {
            throw new \RuntimeException('Failed to destroy server via Hetzner API.');
        }
    }

    private function destroyDigitalOceanServer(CloudDeployment $deployment): void
    {
        $response = Http::withToken($deployment->provider_api_key)
            ->timeout(15)
            ->delete("https://api.digitalocean.com/v2/droplets/{$deployment->provider_server_id}");

        if (! $response->successful() && $response->status() !== 404) {
            throw new \RuntimeException('Failed to destroy server via DigitalOcean API.');
        }
    }
}
