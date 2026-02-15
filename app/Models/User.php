<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'github_id',
        'github_username',
        'avatar_url',
        'notification_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

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
            'two_factor_confirmed_at' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * @return HasMany<DeviceAuthorization, $this>
     */
    public function deviceAuthorizations(): HasMany
    {
        return $this->hasMany(DeviceAuthorization::class);
    }

    /**
     * @return HasMany<CloudDeployment, $this>
     */
    public function cloudDeployments(): HasMany
    {
        return $this->hasMany(CloudDeployment::class);
    }

    /**
     * @return HasMany<OauthDeviceCode, $this>
     */
    public function oauthDeviceCodes(): HasMany
    {
        return $this->hasMany(OauthDeviceCode::class);
    }
}
