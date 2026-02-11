<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaApartmentDetail extends Model
{
    use HasFactory;

    protected $table = 'ta_apartment_details';

    protected $fillable = [
        'apartment_id',
        'city_id',
        'lang',
        'unified_payload',
        'prices_totals_payload',
        'prices_graph_payload',
        'fetched_at',
    ];

    protected $casts = [
        'unified_payload' => 'array',
        'prices_totals_payload' => 'array',
        'prices_graph_payload' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function apartment()
    {
        return $this->belongsTo(TaApartment::class, 'apartment_id', 'apartment_id');
    }
}
