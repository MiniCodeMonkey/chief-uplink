<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderApiKey extends Model
{
    /** @use HasFactory<\Database\Factories\ProviderApiKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'api_key',
        'masked_key',
        'account_name',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
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
     * Generate a masked representation of the API key.
     */
    public static function maskKey(string $key): string
    {
        $length = strlen($key);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        $prefix = substr($key, 0, 3);
        $suffix = substr($key, -6);

        return $prefix.'...'.$suffix;
    }
}
