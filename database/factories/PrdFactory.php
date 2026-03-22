<?php

namespace Database\Factories;

use App\Enums\PrdStatus;
use App\Models\Device;
use App\Models\Prd;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prd>
 */
class PrdFactory extends Factory
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
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'status' => PrdStatus::Draft,
            'content' => fake()->paragraphs(3, true),
            'progress' => null,
            'chat_history' => [],
            'session_id' => null,
        ];
    }

    /**
     * Indicate that the PRD has chat history.
     */
    public function withChatHistory(): static
    {
        return $this->state(fn (array $attributes) => [
            'chat_history' => [
                ['role' => 'user', 'content' => 'What should we build?'],
                ['role' => 'assistant', 'content' => 'Let me help you define the requirements.'],
            ],
        ]);
    }
}
