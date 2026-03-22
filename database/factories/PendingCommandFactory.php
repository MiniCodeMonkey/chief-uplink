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
            'message_id' => fake()->uuid(),
            'type' => fake()->randomElement(['cmd.prd.create', 'cmd.run.start', 'cmd.settings.get']),
            'payload' => ['key' => fake()->word()],
        ];
    }
}
