<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Model;

class TaContractChange extends Model
{
    protected $table = 'ta_contract_changes';

    public $timestamps = false;

    protected $fillable = [
        'endpoint',
        'city_id',
        'lang',
        'old_payload_hash',
        'new_payload_hash',
        'old_top_keys',
        'new_top_keys',
        'old_data_keys',
        'new_data_keys',
        'payload_cache_id',
        'detected_at',
    ];

    protected $casts = [
        'old_top_keys' => 'array',
        'new_top_keys' => 'array',
        'old_data_keys' => 'array',
        'new_data_keys' => 'array',
        'detected_at' => 'datetime',
    ];

    public function payloadCache()
    {
        return $this->belongsTo(TaPayloadCache::class, 'payload_cache_id');
    }
}
