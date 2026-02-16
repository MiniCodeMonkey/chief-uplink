<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProviderApiKey>
 */
class ProviderApiKeyFactory extends Factory
{
    public function definition(): array
    {
        $provider = fake()->randomElement(['hetzner', 'digitalocean']);
        $key = Str::random(64);

        return [
            'user_id' => User::factory(),
            'provider' => $provider,
            'api_key' => $key,
            'masked_key' => \App\Models\ProviderApiKey::maskKey($key),
            'account_name' => fake()->company(),
        ];
    }

    public function hetzner(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'hetzner',
        ]);
    }

    public function digitalocean(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'digitalocean',
        ]);
    }
}
