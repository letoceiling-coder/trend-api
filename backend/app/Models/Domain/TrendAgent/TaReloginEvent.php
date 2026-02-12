<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Model;

class TaReloginEvent extends Model
{
    protected $table = 'ta_relogin_events';

    public $timestamps = true;

    protected $fillable = [
        'attempted_at',
        'success',
        'city_id',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
        'success' => 'boolean',
    ];
}
