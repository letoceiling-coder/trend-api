<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaSyncRun extends Model
{
    use HasFactory;

    protected $table = 'ta_sync_runs';

    protected $fillable = [
        'provider',
        'scope',
        'city_id',
        'lang',
        'status',
        'started_at',
        'finished_at',
        'items_fetched',
        'items_saved',
        'error_message',
        'error_context',
        'error_code',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'items_fetched' => 'integer',
        'items_saved' => 'integer',
        'error_context' => 'array',
    ];

    protected $attributes = [
        'provider' => 'trendagent',
        'status' => 'running',
        'items_fetched' => 0,
        'items_saved' => 0,
    ];
}
