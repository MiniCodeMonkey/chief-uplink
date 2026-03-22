<?php

namespace Database\Factories;

use App\Models\DeviceCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeviceCode>
 */
class DeviceCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_code' => Str::random(40),
            'user_code' => strtoupper(Str::random(8)),
            'device_name' => fake()->word().' '.fake()->randomElement(['MacBook', 'Desktop', 'Server']),
            'user_id' => null,
            'team_id' => null,
            'expires_at' => now()->addMinutes(15),
            'approved_at' => null,
        ];
    }

    /**
     * Indicate that the device code has been approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_at' => now(),
        ]);
    }
}
