<?php

namespace Database\Factories;

use App\Enums\CloudProvider;
use App\Models\CloudProviderCredential;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloudProviderCredential>
 */
class CloudProviderCredentialFactory extends Factory
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
            'provider' => fake()->randomElement(CloudProvider::cases()),
            'api_key' => fake()->sha256(),
            'name' => fake()->word().' '.fake()->randomElement(['Production', 'Staging', 'Dev']),
        ];
    }
}
