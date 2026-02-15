<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogCache extends Model
{
    /** @use HasFactory<\Database\Factories\LogCacheFactory> */
    use HasFactory;

    protected $table = 'log_cache';

    protected $fillable = [
        'device_authorization_id',
        'project_slug',
        'log_type',
        'story_id',
        'content',
    ];

    /**
     * @return BelongsTo<DeviceAuthorization, $this>
     */
    public function deviceAuthorization(): BelongsTo
    {
        return $this->belongsTo(DeviceAuthorization::class);
    }
}
