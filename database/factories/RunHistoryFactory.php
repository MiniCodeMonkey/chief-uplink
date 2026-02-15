<?php

namespace Database\Factories;

use App\Models\DeviceAuthorization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RunHistory>
 */
class RunHistoryFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->numberBetween(5, 15);
        $startedAt = fake()->dateTimeBetween('-30 days', '-1 hour');
        $duration = fake()->numberBetween(300, 7200);

        return [
            'device_authorization_id' => DeviceAuthorization::factory(),
            'project_slug' => fake()->randomElement(['chief-uplink', 'api-gateway', 'data-pipeline', 'frontend-app']),
            'prd_name' => 'v'.fake()->numberBetween(1, 3).'.'.fake()->numberBetween(0, 9).' Features',
            'status' => 'completed',
            'stories_completed' => $total,
            'stories_total' => $total,
            'story_results' => null,
            'duration_seconds' => $duration,
            'tokens_used' => fake()->numberBetween(50000, 500000),
            'error_message' => null,
            'started_at' => $startedAt,
            'finished_at' => (clone $startedAt)->modify("+{$duration} seconds"),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'stories_completed' => $attributes['stories_total'],
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $completed = fake()->numberBetween(0, max(0, $attributes['stories_total'] - 1));

            return [
                'status' => 'failed',
                'stories_completed' => $completed,
                'error_message' => fake()->randomElement([
                    'Test suite failed: 3 tests failing in AuthControllerTest',
                    'Build error: TypeScript compilation failed with 2 errors',
                    'Timeout: story exceeded maximum iteration count',
                    'API rate limit exceeded — Claude quota exhausted',
                ]),
            ];
        });
    }

    public function paused(): static
    {
        return $this->state(function (array $attributes) {
            $completed = fake()->numberBetween(1, max(1, $attributes['stories_total'] - 1));

            return [
                'status' => 'paused',
                'stories_completed' => $completed,
                'finished_at' => null,
            ];
        });
    }

    public function stopped(): static
    {
        return $this->state(function (array $attributes) {
            $completed = fake()->numberBetween(0, max(0, $attributes['stories_total'] - 1));

            return [
                'status' => 'stopped',
                'stories_completed' => $completed,
            ];
        });
    }
}
