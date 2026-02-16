<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeviceAuthorization extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceAuthorizationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_name',
        'os',
        'arch',
        'chief_version',
        'refresh_token_hash',
        'previous_refresh_token_hash',
        'last_ip',
        'last_connected_at',
        'last_heartbeat_at',
        'session_id',
        'is_online',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'last_connected_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CachedProjectState, $this>
     */
    public function cachedProjectStates(): HasMany
    {
        return $this->hasMany(CachedProjectState::class);
    }

    /**
     * @return HasMany<RunHistory, $this>
     */
    public function runHistory(): HasMany
    {
        return $this->hasMany(RunHistory::class);
    }

    /**
     * @return HasMany<LogCache, $this>
     */
    public function logCache(): HasMany
    {
        return $this->hasMany(LogCache::class);
    }

    /**
     * @return HasOne<CloudDeployment, $this>
     */
    public function cloudDeployment(): HasOne
    {
        return $this->hasOne(CloudDeployment::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
