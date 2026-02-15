<?php

namespace Database\Factories;

use App\Models\DeviceAuthorization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CloudDeployment>
 */
class CloudDeploymentFactory extends Factory
{
    public function definition(): array
    {
        $provider = fake()->randomElement(['hetzner', 'digitalocean']);
        $regions = $provider === 'hetzner'
            ? ['nbg1', 'fsn1', 'hel1', 'ash']
            : ['nyc1', 'sfo3', 'lon1', 'ams3', 'sgp1'];
        $tiers = $provider === 'hetzner'
            ? ['cx22', 'cx32', 'cx42']
            : ['s-2vcpu-4gb', 's-4vcpu-8gb', 's-8vcpu-16gb'];
        $costs = $provider === 'hetzner'
            ? ['5.49', '9.49', '17.49']
            : ['24.00', '48.00', '96.00'];

        $tierIndex = array_rand($tiers);

        return [
            'user_id' => User::factory(),
            'device_authorization_id' => DeviceAuthorization::factory(),
            'provider' => $provider,
            'provider_server_id' => (string) fake()->numberBetween(10000000, 99999999),
            'provider_api_key' => Str::random(64),
            'region' => $regions[array_rand($regions)],
            'tier' => $tiers[$tierIndex],
            'ip_address' => fake()->ipv4(),
            'status' => 'active',
            'monthly_cost' => $costs[$tierIndex],
            'setup_token' => null,
            'setup_token_expires_at' => null,
        ];
    }

    public function provisioning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'provisioning',
            'ip_address' => null,
            'provider_server_id' => null,
            'device_authorization_id' => null,
            'setup_token' => Str::random(64),
            'setup_token_expires_at' => now()->addMinutes(10),
        ]);
    }

    public function destroyed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'destroyed',
        ]);
    }

    public function hetzner(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'hetzner',
            'region' => 'nbg1',
            'tier' => 'cx22',
            'monthly_cost' => '5.49',
        ]);
    }

    public function digitalocean(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'digitalocean',
            'region' => 'nyc1',
            'tier' => 's-2vcpu-4gb',
            'monthly_cost' => '24.00',
        ]);
    }
}
