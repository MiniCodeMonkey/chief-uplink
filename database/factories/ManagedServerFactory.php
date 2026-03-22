<?php

namespace Database\Factories;

use App\Enums\CloudProvider;
use App\Enums\ServerStatus;
use App\Models\CloudProviderCredential;
use App\Models\ManagedServer;
use App\Models\SshKey;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManagedServer>
 */
class ManagedServerFactory extends Factory
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
            'credential_id' => CloudProviderCredential::factory(),
            'ssh_key_id' => SshKey::factory(),
            'name' => fake()->word().'-server',
            'provider' => fake()->randomElement(CloudProvider::cases()),
            'region_id' => fake()->randomElement(['nbg1', 'fsn1', 'hel1', 'nyc1']),
            'size_id' => fake()->randomElement(['cx22', 'cx32', 's-1vcpu-1gb']),
            'status' => ServerStatus::Provisioning,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ServerStatus::Active,
            'provider_server_id' => (string) fake()->randomNumber(8),
            'ip_address' => fake()->ipv4(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => ServerStatus::Failed,
        ]);
    }
}
