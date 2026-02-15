<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudDeployment extends Model
{
    /** @use HasFactory<\Database\Factories\CloudDeploymentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_authorization_id',
        'provider',
        'provider_server_id',
        'provider_api_key',
        'region',
        'tier',
        'ip_address',
        'status',
        'monthly_cost',
        'setup_token',
        'setup_token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_api_key' => 'encrypted',
            'monthly_cost' => 'decimal:2',
            'setup_token_expires_at' => 'datetime',
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
     * @return BelongsTo<DeviceAuthorization, $this>
     */
    public function deviceAuthorization(): BelongsTo
    {
        return $this->belongsTo(DeviceAuthorization::class);
    }
}
