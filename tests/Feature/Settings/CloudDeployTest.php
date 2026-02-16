<?php

use App\Models\CloudDeployment;
use App\Models\ProviderApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('cloud deploy page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('cloud-deploy.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/CloudDeploy')
        ->has('providerKeys')
        ->has('supportedProviders')
    );
});

test('cloud deploy page requires authentication', function () {
    $response = $this->get(route('cloud-deploy.create'));

    $response->assertRedirect(route('login'));
});

test('cloud deploy page lists existing provider keys', function () {
    $user = User::factory()->create();
    ProviderApiKey::factory()->hetzner()->create([
        'user_id' => $user->id,
        'masked_key' => 'abc...xyz123',
        'account_name' => 'My Hetzner',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('cloud-deploy.create'));

    $response->assertInertia(fn ($page) => $page
        ->has('providerKeys', 1)
        ->where('providerKeys.0.provider', 'hetzner')
    );
});

// --- Regions endpoint ---

test('can fetch hetzner regions', function () {
    Http::fake([
        'api.hetzner.cloud/v1/locations' => Http::response([
            'locations' => [
                [
                    'name' => 'nbg1',
                    'city' => 'Nuremberg',
                    'country' => 'DE',
                    'description' => 'Nuremberg 1 DC3',
                ],
                [
                    'name' => 'fsn1',
                    'city' => 'Falkenstein',
                    'country' => 'DE',
                    'description' => 'Falkenstein 1 DC14',
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    ProviderApiKey::factory()->hetzner()->create(['user_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.regions'), ['provider' => 'hetzner']);

    $response->assertOk();
    $response->assertJsonStructure(['regions' => [['id', 'name', 'description']]]);
    $response->assertJsonCount(2, 'regions');
});

test('can fetch digitalocean regions', function () {
    Http::fake([
        'api.digitalocean.com/v2/regions*' => Http::response([
            'regions' => [
                [
                    'slug' => 'nyc1',
                    'name' => 'New York 1',
                    'available' => true,
                ],
                [
                    'slug' => 'sfo3',
                    'name' => 'San Francisco 3',
                    'available' => true,
                ],
                [
                    'slug' => 'ams2',
                    'name' => 'Amsterdam 2',
                    'available' => false,
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    ProviderApiKey::factory()->digitalocean()->create(['user_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.regions'), ['provider' => 'digitalocean']);

    $response->assertOk();
    // Only available regions should be returned
    $response->assertJsonCount(2, 'regions');
});

test('fetching regions without api key returns error', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.regions'), ['provider' => 'hetzner']);

    $response->assertUnprocessable();
    $response->assertJson(['error' => 'No API key configured for this provider.']);
});

test('fetching regions requires valid provider', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.regions'), ['provider' => 'aws']);

    $response->assertUnprocessable();
});

// --- Tiers endpoint ---

test('can fetch hetzner tiers', function () {
    Http::fake([
        'api.hetzner.cloud/v1/server_types*' => Http::response([
            'server_types' => [
                [
                    'name' => 'cx22',
                    'cores' => 2,
                    'memory' => 4096,
                    'disk' => 40,
                    'prices' => [['price_monthly' => ['gross' => '5.49']]],
                ],
                [
                    'name' => 'cx32',
                    'cores' => 4,
                    'memory' => 8192,
                    'disk' => 80,
                    'prices' => [['price_monthly' => ['gross' => '9.49']]],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    ProviderApiKey::factory()->hetzner()->create(['user_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.tiers'), [
            'provider' => 'hetzner',
            'region' => 'nbg1',
        ]);

    $response->assertOk();
    $response->assertJsonStructure(['tiers' => [['id', 'name', 'cpu', 'ram', 'disk', 'monthly_cost', 'recommended']]]);
});

test('can fetch digitalocean tiers', function () {
    Http::fake([
        'api.digitalocean.com/v2/sizes*' => Http::response([
            'sizes' => [
                [
                    'slug' => 's-2vcpu-4gb',
                    'vcpus' => 2,
                    'memory' => 4096,
                    'disk' => 80,
                    'price_monthly' => 24.00,
                    'available' => true,
                    'regions' => ['nyc1', 'sfo3'],
                ],
                [
                    'slug' => 's-4vcpu-8gb',
                    'vcpus' => 4,
                    'memory' => 8192,
                    'disk' => 160,
                    'price_monthly' => 48.00,
                    'available' => true,
                    'regions' => ['nyc1'],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    ProviderApiKey::factory()->digitalocean()->create(['user_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.tiers'), [
            'provider' => 'digitalocean',
            'region' => 'nyc1',
        ]);

    $response->assertOk();
    $response->assertJsonCount(2, 'tiers');
});

test('digitalocean tiers are filtered by region', function () {
    Http::fake([
        'api.digitalocean.com/v2/sizes*' => Http::response([
            'sizes' => [
                [
                    'slug' => 's-2vcpu-4gb',
                    'vcpus' => 2,
                    'memory' => 4096,
                    'disk' => 80,
                    'price_monthly' => 24.00,
                    'available' => true,
                    'regions' => ['nyc1', 'sfo3'],
                ],
                [
                    'slug' => 's-4vcpu-8gb',
                    'vcpus' => 4,
                    'memory' => 8192,
                    'disk' => 160,
                    'price_monthly' => 48.00,
                    'available' => true,
                    'regions' => ['sfo3'],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    ProviderApiKey::factory()->digitalocean()->create(['user_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.tiers'), [
            'provider' => 'digitalocean',
            'region' => 'nyc1',
        ]);

    $response->assertOk();
    // Only sizes available in nyc1
    $response->assertJsonCount(1, 'tiers');
});

test('fetching tiers requires region', function () {
    $user = User::factory()->create();
    ProviderApiKey::factory()->hetzner()->create(['user_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.tiers'), ['provider' => 'hetzner']);

    $response->assertUnprocessable();
});

// --- Deploy endpoint ---

test('can deploy a hetzner server', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers' => Http::response([
            'server' => [
                'id' => 12345678,
                'public_net' => [
                    'ipv4' => ['ip' => '1.2.3.4'],
                ],
            ],
        ], 201),
    ]);

    $user = User::factory()->create();
    ProviderApiKey::factory()->hetzner()->create(['user_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.deploy'), [
            'provider' => 'hetzner',
            'region' => 'nbg1',
            'tier' => 'cx22',
            'monthly_cost' => 5.49,
        ]);

    $response->assertOk();
    $response->assertJsonStructure(['deployment_id', 'provider_server_id', 'ip_address', 'status']);
    $response->assertJson(['status' => 'provisioning']);

    // Check deployment record
    $deployment = CloudDeployment::where('user_id', $user->id)->first();
    expect($deployment)->not->toBeNull();
    expect($deployment->provider)->toBe('hetzner');
    expect($deployment->region)->toBe('nbg1');
    expect($deployment->tier)->toBe('cx22');
    expect($deployment->status)->toBe('provisioning');
    expect($deployment->provider_server_id)->toBe('12345678');
    expect($deployment->ip_address)->toBe('1.2.3.4');
    expect($deployment->setup_token)->not->toBeNull(); // Token exists until VPS exchanges it
    expect($deployment->setup_token_expires_at)->not->toBeNull();
});

test('can deploy a digitalocean server', function () {
    Http::fake([
        'api.digitalocean.com/v2/droplets' => Http::response([
            'droplet' => [
                'id' => 87654321,
                'networks' => [
                    'v4' => [
                        ['type' => 'public', 'ip_address' => '5.6.7.8'],
                    ],
                ],
            ],
        ], 202),
    ]);

    $user = User::factory()->create();
    ProviderApiKey::factory()->digitalocean()->create(['user_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.deploy'), [
            'provider' => 'digitalocean',
            'region' => 'nyc1',
            'tier' => 's-2vcpu-4gb',
            'monthly_cost' => 24.00,
        ]);

    $response->assertOk();
    $response->assertJson(['status' => 'provisioning']);

    $deployment = CloudDeployment::where('user_id', $user->id)->first();
    expect($deployment->provider)->toBe('digitalocean');
    expect($deployment->provider_server_id)->toBe('87654321');
});

test('deploy generates setup token', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers' => Http::response([
            'server' => [
                'id' => 12345678,
                'public_net' => [
                    'ipv4' => ['ip' => '1.2.3.4'],
                ],
            ],
        ], 201),
    ]);

    $user = User::factory()->create();
    ProviderApiKey::factory()->hetzner()->create(['user_id' => $user->id]);

    $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.deploy'), [
            'provider' => 'hetzner',
            'region' => 'nbg1',
            'tier' => 'cx22',
            'monthly_cost' => 5.49,
        ]);

    // The cloud-init script uses the setup token — verify the request included user_data
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.hetzner.cloud/v1/servers'
            && str_contains($request['user_data'] ?? '', '/api/oauth/device/exchange');
    });
});

test('deploy without api key returns error', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.deploy'), [
            'provider' => 'hetzner',
            'region' => 'nbg1',
            'tier' => 'cx22',
            'monthly_cost' => 5.49,
        ]);

    $response->assertUnprocessable();
    $response->assertJson(['error' => 'No API key configured for this provider.']);
});

test('deploy with provider api error returns error', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers' => Http::response([
            'error' => ['message' => 'insufficient funds'],
        ], 402),
    ]);

    $user = User::factory()->create();
    ProviderApiKey::factory()->hetzner()->create(['user_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.deploy'), [
            'provider' => 'hetzner',
            'region' => 'nbg1',
            'tier' => 'cx22',
            'monthly_cost' => 5.49,
        ]);

    $response->assertStatus(500);
    $response->assertJsonStructure(['error']);

    // Failed deployment should be marked as destroyed
    $deployment = CloudDeployment::where('user_id', $user->id)->first();
    expect($deployment->status)->toBe('destroyed');
});

test('deploy validates required fields', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.deploy'), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['provider', 'region', 'tier', 'monthly_cost']);
});

test('deploy validates unsupported provider', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('cloud-deploy.deploy'), [
            'provider' => 'aws',
            'region' => 'us-east-1',
            'tier' => 't2.micro',
            'monthly_cost' => 8.50,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['provider']);
});

// --- Status endpoint ---

test('can check deployment status', function () {
    $user = User::factory()->create();
    $deployment = CloudDeployment::factory()->hetzner()->create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('cloud-deploy.status', $deployment->id));

    $response->assertOk();
    $response->assertJson([
        'id' => $deployment->id,
        'status' => 'active',
        'provider' => 'hetzner',
    ]);
});

test('status check polls provider api for provisioning servers', function () {
    Http::fake([
        'api.hetzner.cloud/v1/servers/*' => Http::response([
            'server' => [
                'status' => 'running',
                'public_net' => [
                    'ipv4' => ['ip' => '1.2.3.4'],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $deployment = CloudDeployment::factory()->hetzner()->create([
        'user_id' => $user->id,
        'status' => 'provisioning',
        'provider_server_id' => '12345',
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('cloud-deploy.status', $deployment->id));

    $response->assertOk();
    $response->assertJson(['status' => 'active']);

    // Verify DB was updated
    $deployment->refresh();
    expect($deployment->status)->toBe('active');
    expect($deployment->ip_address)->toBe('1.2.3.4');
});

test('cannot check status of another users deployment', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $deployment = CloudDeployment::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('cloud-deploy.status', $deployment->id));

    $response->assertNotFound();
});

test('deploy requires authentication', function () {
    $response = $this->postJson(route('cloud-deploy.deploy'), [
        'provider' => 'hetzner',
        'region' => 'nbg1',
        'tier' => 'cx22',
        'monthly_cost' => 5.49,
    ]);

    $response->assertUnauthorized();
});
