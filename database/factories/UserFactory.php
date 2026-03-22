<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Enums\ThemePreference;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'github_id' => null,
            'github_token' => null,
            'avatar_url' => null,
            'last_visited_url' => null,
            'theme_preference' => ThemePreference::System,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $team = Team::create([
                'name' => "{$user->name}'s Team",
                'owner_id' => $user->id,
            ]);

            $user->teams()->attach($team, ['role' => TeamRole::Owner->value]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a user authenticated via GitHub only.
     */
    public function githubOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'github_id' => (string) fake()->unique()->randomNumber(8),
            'github_token' => Str::random(40),
            'avatar_url' => fake()->imageUrl(200, 200),
        ]);
    }
}
