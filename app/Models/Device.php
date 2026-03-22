<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'managed_server_id', 'name', 'os', 'arch', 'chief_version', 'access_token', 'refresh_token_hash', 'token_expires_at', 'last_seen_at', 'connected'])]
#[Hidden(['access_token', 'refresh_token_hash'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'connected' => 'boolean',
        ];
    }

    /**
     * Find a device by its plain-text access token.
     */
    public static function findByToken(string $token): ?static
    {
        $hash = hash('sha256', $token);

        return static::where('access_token', $hash)->first();
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<Prd, $this>
     */
    public function prds(): HasMany
    {
        return $this->hasMany(Prd::class);
    }

    /**
     * @return HasMany<Run, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    /**
     * @return HasMany<PendingCommand, $this>
     */
    public function pendingCommands(): HasMany
    {
        return $this->hasMany(PendingCommand::class);
    }
}
