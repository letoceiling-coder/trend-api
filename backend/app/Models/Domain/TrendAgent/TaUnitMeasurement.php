<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaUnitMeasurement extends Model
{
    use HasFactory;

    protected $table = 'ta_unit_measurements';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'code',
        'currency',
        'measurement',
        'raw',
    ];

    protected $casts = [
        'raw' => 'array',
    ];
}
