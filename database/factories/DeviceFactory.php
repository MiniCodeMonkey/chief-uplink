<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'managed_server_id' => null,
            'name' => fake()->word().' '.fake()->randomElement(['MacBook', 'Desktop', 'Server']),
            'os' => fake()->randomElement(['darwin', 'linux', 'windows']),
            'arch' => fake()->randomElement(['amd64', 'arm64']),
            'chief_version' => fake()->semver(),
            'access_token' => hash('sha256', fake()->unique()->uuid()),
            'refresh_token_hash' => hash('sha256', fake()->unique()->uuid()),
            'token_expires_at' => now()->addDays(30),
            'last_seen_at' => null,
            'connected' => false,
        ];
    }

    /**
     * Indicate that the device is connected.
     */
    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'connected' => true,
            'last_seen_at' => now(),
        ]);
    }
}
