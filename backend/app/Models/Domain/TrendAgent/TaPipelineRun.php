<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TaPipelineRun extends Model
{
    protected $table = 'ta_pipeline_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'city_id',
        'lang',
        'requested_by',
        'params',
        'status',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected $casts = [
        'params' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public static function createRecord(array $params, string $status, ?string $requestedBy = null): self
    {
        $id = (string) Str::uuid();
        $run = self::create([
            'id' => $id,
            'city_id' => $params['city_id'] ?? null,
            'lang' => $params['lang'] ?? null,
            'requested_by' => $requestedBy,
            'params' => $params,
            'status' => $status,
            'started_at' => now(),
        ]);
        return $run;
    }
}
