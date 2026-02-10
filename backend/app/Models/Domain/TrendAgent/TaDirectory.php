<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaDirectory extends Model
{
    use HasFactory;

    protected $table = 'ta_directories';

    protected $fillable = [
        'type',
        'city_id',
        'lang',
        'payload',
        'fetched_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'fetched_at' => 'datetime',
    ];
}
