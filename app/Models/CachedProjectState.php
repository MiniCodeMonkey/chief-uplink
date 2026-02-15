<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CachedProjectState extends Model
{
    /** @use HasFactory<\Database\Factories\CachedProjectStateFactory> */
    use HasFactory;

    protected $table = 'cached_project_state';

    protected $fillable = [
        'device_authorization_id',
        'project_slug',
        'project_name',
        'git_branch',
        'last_commit_hash',
        'last_commit_message',
        'status',
        'current_prd_name',
        'stories_completed',
        'stories_total',
        'story_details',
        'active_sessions',
        'recent_activity',
    ];

    protected function casts(): array
    {
        return [
            'stories_completed' => 'integer',
            'stories_total' => 'integer',
            'story_details' => 'array',
            'active_sessions' => 'integer',
            'recent_activity' => 'array',
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
