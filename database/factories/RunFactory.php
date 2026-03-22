<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Enums\StoryStatus;
use App\Models\Device;
use App\Models\Prd;
use App\Models\Run;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
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
            'prd_id' => Prd::factory(),
            'status' => RunStatus::Pending,
            'stories' => $this->generateStories(3),
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => RunStatus::Running,
            'started_at' => now()->subMinutes(10),
            'stories' => $this->generateStories(5, 1),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => RunStatus::Completed,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'stories' => $this->generateStoriesAllDone(4),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => RunStatus::Failed,
            'started_at' => now()->subMinutes(30),
            'completed_at' => now(),
        ]);
    }

    public function stopped(): static
    {
        return $this->state(fn () => [
            'status' => RunStatus::Stopped,
            'started_at' => now()->subMinutes(15),
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generateStories(int $count, int $inProgressIndex = -1): array
    {
        $stories = [];
        for ($i = 0; $i < $count; $i++) {
            $stories[] = [
                'id' => 'US-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'title' => fake()->sentence(4),
                'priority' => fake()->numberBetween(1, 5),
                'description' => fake()->paragraph(),
                'acceptance_criteria' => [fake()->sentence(), fake()->sentence()],
                'status' => $i === $inProgressIndex ? StoryStatus::InProgress->value : StoryStatus::Pending->value,
                'progress_notes' => null,
                'iteration_count' => 0,
            ];
        }

        return $stories;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generateStoriesAllDone(int $count): array
    {
        $stories = [];
        for ($i = 0; $i < $count; $i++) {
            $stories[] = [
                'id' => 'US-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'title' => fake()->sentence(4),
                'priority' => fake()->numberBetween(1, 5),
                'description' => fake()->paragraph(),
                'acceptance_criteria' => [fake()->sentence(), fake()->sentence()],
                'status' => StoryStatus::Done->value,
                'progress_notes' => 'Completed successfully.',
                'iteration_count' => fake()->numberBetween(1, 3),
            ];
        }

        return $stories;
    }
}
