<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OauthDeviceCode extends Model
{
    /** @use HasFactory<\Database\Factories\OauthDeviceCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'device_code',
        'user_code',
        'device_name',
        'user_id',
        'status',
        'expires_at',
        'last_polled_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_polled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
