<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TaSsoSession extends Model
{
    use HasFactory;

    protected $table = 'ta_sso_sessions';

    protected $fillable = [
        'provider',
        'phone',
        'app_id',
        'city_id',
        'refresh_token',
        'refresh_expires_at',
        'last_login_at',
        'last_auth_token_at',
        'is_active',
        'invalidated_at',
    ];

    protected $casts = [
        'refresh_expires_at' => 'datetime',
        'last_login_at'      => 'datetime',
        'last_auth_token_at' => 'datetime',
        'is_active'          => 'boolean',
        'invalidated_at'     => 'datetime',
    ];

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value === null ? null : Crypt::encryptString($value);
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
