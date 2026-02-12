<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaApartment extends Model
{
    use HasFactory;

    protected $table = 'ta_apartments';

    protected $fillable = [
        'apartment_id',
        'block_id',
        'guid',
        'title',
        'rooms',
        'area_total',
        'floor',
        'price',
        'status',
        'city_id',
        'lang',
        'raw',
        'normalized',
        'payload_hash',
        'fetched_at',
    ];

    protected $casts = [
        'rooms' => 'integer',
        'area_total' => 'decimal:2',
        'floor' => 'integer',
        'price' => 'integer',
        'raw' => 'array',
        'normalized' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function block()
    {
        return $this->belongsTo(TaBlock::class, 'block_id', 'block_id');
    }
}
