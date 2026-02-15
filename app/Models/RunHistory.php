<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunHistory extends Model
{
    /** @use HasFactory<\Database\Factories\RunHistoryFactory> */
    use HasFactory;

    protected $table = 'run_history';

    protected $fillable = [
        'device_authorization_id',
        'project_slug',
        'prd_name',
        'status',
        'stories_completed',
        'stories_total',
        'story_results',
        'duration_seconds',
        'tokens_used',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'stories_completed' => 'integer',
            'stories_total' => 'integer',
            'story_results' => 'array',
            'duration_seconds' => 'integer',
            'tokens_used' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DeviceAuthorization, $this>
     */
    public function deviceAuthorization(): BelongsTo
    {
        return $this->belongsTo(DeviceAuthorization::class);
    }
}
