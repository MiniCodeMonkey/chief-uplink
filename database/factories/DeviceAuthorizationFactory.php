<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceAuthorization>
 */
class DeviceAuthorizationFactory extends Factory
{
    public function definition(): array
    {
        $osOptions = ['linux', 'darwin', 'windows'];
        $archOptions = ['amd64', 'arm64'];

        return [
            'user_id' => User::factory(),
            'device_name' => fake()->randomElement(['hetzner-vps', 'macbook-pro', 'dev-server', 'home-desktop', 'work-laptop', 'cloud-runner']),
            'os' => fake()->randomElement($osOptions),
            'arch' => fake()->randomElement($archOptions),
            'chief_version' => '0.'.fake()->numberBetween(4, 6).'.'.fake()->numberBetween(0, 9),
            'refresh_token_hash' => Hash::make(Str::random(64)),
            'last_ip' => fake()->ipv4(),
            'last_connected_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'is_online' => false,
            'revoked_at' => null,
        ];
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => true,
            'last_connected_at' => now(),
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => false,
            'last_connected_at' => fake()->dateTimeBetween('-7 days', '-1 hour'),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => false,
            'revoked_at' => now(),
        ]);
    }
}
