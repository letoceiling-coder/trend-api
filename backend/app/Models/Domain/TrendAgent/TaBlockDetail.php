<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaBlockDetail extends Model
{
    use HasFactory;

    protected $table = 'ta_block_details';

    protected $fillable = [
        'block_id',
        'city_id',
        'lang',
        'unified_payload',
        'advantages_payload',
        'nearby_places_payload',
        'bank_payload',
        'geo_buildings_payload',
        'apartments_min_price_payload',
        'fetched_at',
    ];

    protected $casts = [
        'unified_payload' => 'array',
        'advantages_payload' => 'array',
        'nearby_places_payload' => 'array',
        'bank_payload' => 'array',
        'geo_buildings_payload' => 'array',
        'apartments_min_price_payload' => 'array',
        'fetched_at' => 'datetime',
    ];

    /**
     * Relation to TaBlock
     */
    public function block()
    {
        return $this->belongsTo(TaBlock::class, 'block_id', 'block_id');
    }
}
