<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
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
            'path' => '/home/user/projects/'.fake()->slug(2),
            'name' => fake()->words(2, true),
            'git_remote' => 'https://github.com/'.fake()->userName().'/'.fake()->slug(2).'.git',
            'git_branch' => 'main',
            'git_sha' => fake()->sha1(),
            'git_status' => fake()->randomElement(['clean', 'dirty']),
            'last_commit' => null,
        ];
    }
}
