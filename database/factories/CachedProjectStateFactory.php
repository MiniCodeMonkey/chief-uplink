<?php

namespace Database\Factories;

use App\Models\DeviceAuthorization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CachedProjectState>
 */
class CachedProjectStateFactory extends Factory
{
    public function definition(): array
    {
        $projectNames = ['chief-uplink', 'api-gateway', 'data-pipeline', 'frontend-app', 'auth-service', 'payment-system', 'notification-hub', 'analytics-engine'];
        $name = fake()->randomElement($projectNames);

        return [
            'device_authorization_id' => DeviceAuthorization::factory(),
            'project_slug' => $name,
            'project_name' => str_replace('-', ' ', ucwords($name, '-')),
            'git_branch' => 'main',
            'last_commit_hash' => substr(fake()->sha1(), 0, 7),
            'last_commit_message' => fake()->randomElement([
                'feat: add user authentication',
                'fix: resolve race condition in queue',
                'refactor: simplify middleware stack',
                'chore: update dependencies',
                'feat: implement webhook handling',
            ]),
            'status' => 'idle',
            'current_prd_name' => null,
            'stories_completed' => 0,
            'stories_total' => 0,
            'story_details' => null,
            'active_sessions' => 0,
            'recent_activity' => null,
        ];
    }

    public function running(): static
    {
        $total = fake()->numberBetween(5, 15);
        $completed = fake()->numberBetween(1, $total - 1);

        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'current_prd_name' => 'v'.fake()->numberBetween(1, 3).'.0 Features',
            'stories_completed' => $completed,
            'stories_total' => $total,
            'story_details' => $this->generateStoryDetails($completed, $total),
            'active_sessions' => 1,
            'recent_activity' => [
                ['event' => 'Story US-00'.($completed).' completed', 'timestamp' => now()->subMinutes(3)->toISOString()],
                ['event' => 'Story US-00'.($completed + 1).' started', 'timestamp' => now()->subMinute()->toISOString()],
            ],
        ]);
    }

    public function idle(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'idle',
            'current_prd_name' => 'v1.0 Features',
            'stories_completed' => 12,
            'stories_total' => 12,
            'active_sessions' => 0,
        ]);
    }

    public function error(): static
    {
        $total = fake()->numberBetween(5, 15);
        $completed = fake()->numberBetween(0, $total - 1);

        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'current_prd_name' => 'v2.0 Redesign',
            'stories_completed' => $completed,
            'stories_total' => $total,
            'story_details' => $this->generateStoryDetails($completed, $total, true),
            'recent_activity' => [
                ['event' => 'Run failed — test suite errors', 'timestamp' => now()->subMinutes(15)->toISOString()],
            ],
        ]);
    }

    public function paused(): static
    {
        $total = fake()->numberBetween(5, 15);
        $completed = fake()->numberBetween(1, $total - 1);

        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
            'current_prd_name' => 'API Improvements',
            'stories_completed' => $completed,
            'stories_total' => $total,
            'story_details' => $this->generateStoryDetails($completed, $total),
            'recent_activity' => [
                ['event' => 'Run paused by user', 'timestamp' => now()->subMinutes(30)->toISOString()],
            ],
        ]);
    }

    public function noPrd(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'no_prd',
            'current_prd_name' => null,
            'stories_completed' => 0,
            'stories_total' => 0,
        ]);
    }

    /**
     * @return list<array{id: string, title: string, status: string}>
     */
    private function generateStoryDetails(int $completed, int $total, bool $withError = false): array
    {
        $stories = [];
        for ($i = 1; $i <= $total; $i++) {
            $status = 'pending';
            if ($i <= $completed) {
                $status = 'completed';
            } elseif ($i === $completed + 1) {
                $status = $withError ? 'failed' : 'in_progress';
            }

            $stories[] = [
                'id' => 'US-'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'title' => fake()->randomElement([
                    'Add user authentication',
                    'Create dashboard layout',
                    'Implement API endpoints',
                    'Set up database schema',
                    'Build notification system',
                    'Add search functionality',
                    'Implement file uploads',
                    'Create admin panel',
                    'Add payment integration',
                    'Build reporting module',
                    'Implement caching layer',
                    'Add webhook support',
                    'Create CLI tool',
                    'Build monitoring dashboard',
                    'Implement rate limiting',
                ]),
                'status' => $status,
            ];
        }

        return $stories;
    }
}
