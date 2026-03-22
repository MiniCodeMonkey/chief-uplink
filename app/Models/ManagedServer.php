<?php

namespace App\Models;

use App\Enums\CloudProvider;
use App\Enums\ServerStatus;
use Database\Factories\ManagedServerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'credential_id', 'ssh_key_id', 'name', 'provider', 'region_id', 'size_id', 'status', 'provider_server_id', 'ip_address'])]
class ManagedServer extends Model
{
    /** @use HasFactory<ManagedServerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => CloudProvider::class,
            'status' => ServerStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<CloudProviderCredential, $this>
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(CloudProviderCredential::class);
    }

    /**
     * @return BelongsTo<SshKey, $this>
     */
    public function sshKey(): BelongsTo
    {
        return $this->belongsTo(SshKey::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
