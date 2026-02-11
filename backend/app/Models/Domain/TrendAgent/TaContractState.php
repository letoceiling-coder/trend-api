<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Model;

class TaContractState extends Model
{
    protected $table = 'ta_contract_state';

    public $timestamps = false;

    protected $fillable = [
        'endpoint',
        'city_id',
        'lang',
        'last_payload_hash',
        'last_top_keys',
        'last_data_keys',
        'updated_at',
    ];

    protected $casts = [
        'last_top_keys' => 'array',
        'last_data_keys' => 'array',
        'updated_at' => 'datetime',
    ];
}
