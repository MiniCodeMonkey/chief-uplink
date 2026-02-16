<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CloudDeployment;
use App\Models\ProviderApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CloudProviderKeyController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['hetzner', 'digitalocean'];

    public function index(Request $request): Response
    {
        $keys = $request->user()
            ->providerApiKeys()
            ->orderBy('provider')
            ->get()
            ->map(fn (ProviderApiKey $key) => [
                'id' => $key->id,
                'provider' => $key->provider,
                'masked_key' => $key->masked_key,
                'account_name' => $key->account_name,
                'created_at' => $key->created_at?->toISOString(),
            ]);

        $deployments = $request->user()
            ->cloudDeployments()
            ->with('deviceAuthorization')
            ->orderByRaw("CASE WHEN status = 'destroyed' THEN 1 ELSE 0 END")
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (CloudDeployment $deployment) => [
                'id' => $deployment->id,
                'provider' => $deployment->provider,
                'region' => $deployment->region,
                'tier' => $deployment->tier,
                'ip_address' => $deployment->ip_address,
                'status' => $deployment->status,
                'monthly_cost' => $deployment->monthly_cost,
                'provider_server_id' => $deployment->provider_server_id,
                'device_name' => $deployment->deviceAuthorization?->device_name,
                'device_is_online' => $deployment->deviceAuthorization?->is_online ?? false,
                'created_at' => $deployment->created_at?->toISOString(),
            ]);

        return Inertia::render('settings/CloudServers', [
            'providerKeys' => $keys,
            'supportedProviders' => self::SUPPORTED_PROVIDERS,
            'deployments' => $deployments,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => [
                'required',
                'string',
                Rule::in(self::SUPPORTED_PROVIDERS),
                Rule::unique('provider_api_keys')->where(function ($query) use ($request) {
                    return $query->where('user_id', $request->user()->id);
                }),
            ],
            'api_key' => ['required', 'string', 'min:10'],
        ], [
            'provider.unique' => 'You already have an API key for this provider. Remove it first to add a new one.',
        ]);

        // Validate the key against the provider's API
        $validation = $this->validateProviderKey($validated['provider'], $validated['api_key']);

        if (! $validation['valid']) {
            return back()->withErrors([
                'api_key' => $validation['error'],
            ]);
        }

        $request->user()->providerApiKeys()->create([
            'provider' => $validated['provider'],
            'api_key' => $validated['api_key'],
            'masked_key' => ProviderApiKey::maskKey($validated['api_key']),
            'account_name' => $validation['account_name'],
        ]);

        return back()->with('success', "API key validated — connected as {$validation['account_name']}.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $key = $request->user()
            ->providerApiKeys()
            ->findOrFail($id);

        $providerName = $this->formatProviderName($key->provider);
        $key->delete();

        return back()->with('success', "{$providerName} API key removed.");
    }

    /**
     * Validate an API key against the provider's API.
     *
     * @return array{valid: bool, error?: string, account_name?: string}
     */
    private function validateProviderKey(string $provider, string $apiKey): array
    {
        try {
            return match ($provider) {
                'hetzner' => $this->validateHetznerKey($apiKey),
                'digitalocean' => $this->validateDigitalOceanKey($apiKey),
                default => ['valid' => false, 'error' => 'Unsupported provider.'],
            };
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => 'Network error — check your connection and try again.'];
        }
    }

    /**
     * @return array{valid: bool, error?: string, account_name?: string}
     */
    private function validateHetznerKey(string $apiKey): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(10)
            ->get('https://api.hetzner.cloud/v1/servers', ['per_page' => 1]);

        if ($response->status() === 401 || $response->status() === 403) {
            return ['valid' => false, 'error' => 'Invalid API key. Please check your Hetzner Cloud API token.'];
        }

        if (! $response->successful()) {
            return ['valid' => false, 'error' => 'Unable to validate key. Hetzner API returned an error.'];
        }

        // Hetzner doesn't return account name in server list, use a generic name
        return ['valid' => true, 'account_name' => 'Hetzner Cloud'];
    }

    /**
     * @return array{valid: bool, error?: string, account_name?: string}
     */
    private function validateDigitalOceanKey(string $apiKey): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(10)
            ->get('https://api.digitalocean.com/v2/account');

        if ($response->status() === 401) {
            return ['valid' => false, 'error' => 'Invalid API key. Please check your DigitalOcean personal access token.'];
        }

        if (! $response->successful()) {
            return ['valid' => false, 'error' => 'Unable to validate key. DigitalOcean API returned an error.'];
        }

        $accountName = $response->json('account.name')
            ?? $response->json('account.email')
            ?? 'DigitalOcean';

        return ['valid' => true, 'account_name' => $accountName];
    }

    private function formatProviderName(string $provider): string
    {
        return match ($provider) {
            'hetzner' => 'Hetzner',
            'digitalocean' => 'DigitalOcean',
            default => ucfirst($provider),
        };
    }
}
