<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaPayloadCache extends Model
{
    use HasFactory;

    protected $table = 'ta_payload_cache';

    protected $fillable = [
        'provider',
        'scope',
        'external_id',
        'endpoint',
        'http_status',
        'city_id',
        'lang',
        'etag',
        'payload',
        'payload_hash',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'provider' => 'trendagent',
    ];

    /**
     * Get decoded payload as array
     */
    public function getPayloadDataAttribute(): ?array
    {
        if (! $this->payload) {
            return null;
        }
        $decoded = json_decode($this->payload, true);
        return is_array($decoded) ? $decoded : null;
    }
}
