<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\PendingCommand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PendingCommand>
 */
class PendingCommandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'type' => fake()->randomElement(['sync.state', 'run.start', 'config.update']),
            'payload' => ['key' => fake()->word()],
        ];
    }
}
