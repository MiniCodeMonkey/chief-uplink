<?php

namespace App\Models;

use App\Enums\TeamRole;
use App\Enums\ThemePreference;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'github_id', 'github_token', 'avatar_url', 'last_visited_url', 'theme_preference'])]
#[Hidden(['password', 'remember_token', 'github_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'github_token' => 'encrypted',
            'theme_preference' => ThemePreference::class,
        ];
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    /**
     * Returns the user's current team, creating a default one if none exists.
     */
    public function currentTeam(): Team
    {
        $team = $this->teams()->first();

        if ($team) {
            return $team;
        }

        $team = Team::create([
            'name' => "{$this->name}'s Team",
            'owner_id' => $this->id,
        ]);

        $this->teams()->attach($team, ['role' => TeamRole::Owner->value]);

        return $team;
    }

    public function isOwnerOf(Team $team): bool
    {
        return $team->owner_id === $this->id;
    }

    public function isMemberOf(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }
}
