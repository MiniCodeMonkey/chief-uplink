<?php

use App\Models\DeviceAuthorization;
use App\Models\OauthDeviceCode;
use App\Models\ProviderApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| OAuth Rate Limiting
|--------------------------------------------------------------------------
*/

describe('OAuth Rate Limiting', function () {
    it('limits device code requests to 10 per IP per 15 minutes', function () {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/oauth/device/code', [
                'device_name' => "device-{$i}",
            ])->assertOk();
        }

        $this->postJson('/api/oauth/device/code', [
            'device_name' => 'overflow-device',
        ])->assertStatus(429);
    });

    it('limits device code entry to 10 per user per 15 minutes', function () {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post('/oauth/device/verify', [
                'user_code' => sprintf('XX%02d-YYYY', $i),
            ]);
        }

        $response = $this->actingAs($user)->post('/oauth/device/verify', [
            'user_code' => 'ZZZZ-YYYY',
        ]);
        $response->assertStatus(429);
    });

    it('enforces minimum 5-second interval for token polling', function () {
        $code = OauthDeviceCode::factory()->create([
            'last_polled_at' => now(),
        ]);

        $response = $this->postJson('/api/oauth/device/token', [
            'device_code' => $code->device_code,
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'slow_down']);
    });

    it('allows polling after 5-second interval', function () {
        $code = OauthDeviceCode::factory()->create([
            'last_polled_at' => now()->subSeconds(6),
        ]);

        $response = $this->postJson('/api/oauth/device/token', [
            'device_code' => $code->device_code,
        ]);

        // Should not be slow_down (will be authorization_pending instead)
        $response->assertStatus(400);
        expect($response->json('error'))->toBe('authorization_pending');
    });
});

/*
|--------------------------------------------------------------------------
| Browser Command Rate Limiting
|--------------------------------------------------------------------------
*/

describe('Browser Command Rate Limiting', function () {
    it('limits browser commands to 60 per user per minute', function () {
        $user = User::factory()->create();
        $device = DeviceAuthorization::factory()->for($user)->online()->create();

        Event::fake();

        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($user)
                ->postJson("/ws/command/{$device->id}", [
                    'type' => 'get_settings',
                    'payload' => [],
                ])->assertOk();
        }

        $response = $this->actingAs($user)
            ->postJson("/ws/command/{$device->id}", [
                'type' => 'get_settings',
                'payload' => [],
            ]);

        $response->assertStatus(429);
    });

    it('includes rate limit headers in command responses', function () {
        $user = User::factory()->create();
        $device = DeviceAuthorization::factory()->for($user)->online()->create();

        Event::fake();

        $response = $this->actingAs($user)
            ->postJson("/ws/command/{$device->id}", [
                'type' => 'get_settings',
                'payload' => [],
            ]);

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit', '60');
        $response->assertHeader('X-RateLimit-Remaining');
    });
});

/*
|--------------------------------------------------------------------------
| Clone/Create Project Rate Limiting
|--------------------------------------------------------------------------
*/

describe('Clone/Create Project Rate Limiting', function () {
    it('limits clone/create to 10 per user per hour', function () {
        $user = User::factory()->create();
        $device = DeviceAuthorization::factory()->for($user)->online()->create();

        Event::fake();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)
                ->postJson("/ws/command/{$device->id}", [
                    'type' => 'clone_repo',
                    'payload' => ['url' => "https://github.com/test/r{$i}.git", 'directory' => "r{$i}"],
                ])->assertOk();
        }

        $response = $this->actingAs($user)
            ->postJson("/ws/command/{$device->id}", [
                'type' => 'clone_repo',
                'payload' => ['url' => 'https://github.com/test/overflow.git', 'directory' => 'overflow'],
            ]);

        $response->assertStatus(429)
            ->assertJson(['error' => 'rate_limited'])
            ->assertHeader('Retry-After');
    });
});

/*
|--------------------------------------------------------------------------
| Cloud Deploy Rate Limiting
|--------------------------------------------------------------------------
*/

describe('Cloud Deploy Rate Limiting', function () {
    it('limits cloud deployments to 5 per user per hour', function () {
        Http::fake([
            'api.hetzner.cloud/v1/servers' => Http::response([
                'server' => [
                    'id' => 12345,
                    'public_net' => ['ipv4' => ['ip' => '1.2.3.4']],
                ],
            ], 201),
        ]);

        $user = User::factory()->create();
        ProviderApiKey::factory()->hetzner()->create(['user_id' => $user->id]);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)
                ->postJson(route('cloud-deploy.deploy'), [
                    'provider' => 'hetzner',
                    'region' => 'nbg1',
                    'tier' => 'cx22',
                    'monthly_cost' => 5.49,
                ])->assertOk();
        }

        $response = $this->actingAs($user)
            ->postJson(route('cloud-deploy.deploy'), [
                'provider' => 'hetzner',
                'region' => 'nbg1',
                'tier' => 'cx22',
                'monthly_cost' => 5.49,
            ]);

        $response->assertStatus(429);
    });
});

/*
|--------------------------------------------------------------------------
| Token Refresh Rate Limiting
|--------------------------------------------------------------------------
*/

describe('Token Refresh Rate Limiting', function () {
    it('limits token refresh to 30 per device per hour', function () {
        $user = User::factory()->create();
        $currentToken = Str::random(64);
        $device = DeviceAuthorization::factory()->create([
            'user_id' => $user->id,
            'refresh_token_hash' => Hash::make($currentToken),
        ]);

        $rateLimited = false;

        // Rotate up to 31 times — the 31st should be rate limited
        for ($i = 0; $i < 31; $i++) {
            $response = $this->postJson('/api/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $currentToken,
            ]);

            if ($response->status() === 429) {
                $rateLimited = true;
                break;
            }

            if ($response->status() === 200) {
                $currentToken = $response->json('refresh_token');
            }
        }

        expect($rateLimited)->toBeTrue('Token refresh should be rate limited after 30 attempts');
    });
});

/*
|--------------------------------------------------------------------------
| Rate Limit Response Format
|--------------------------------------------------------------------------
*/

describe('Rate Limit Response Format', function () {
    it('returns 429 with Retry-After header for rate limited requests', function () {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/oauth/device/code', [
                'device_name' => "device-{$i}",
            ]);
        }

        $response = $this->postJson('/api/oauth/device/code', [
            'device_name' => 'overflow',
        ]);

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    });
});
