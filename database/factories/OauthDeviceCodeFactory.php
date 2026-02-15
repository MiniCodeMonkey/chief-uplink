<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OauthDeviceCode>
 */
class OauthDeviceCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_code' => Str::uuid()->toString(),
            'user_code' => strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
            'device_name' => fake()->randomElement(['macbook-pro', 'dev-server', 'home-desktop']),
            'user_id' => null,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
            'last_polled_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
            'status' => 'approved',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subMinutes(5),
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
            'status' => 'denied',
        ]);
    }
}
