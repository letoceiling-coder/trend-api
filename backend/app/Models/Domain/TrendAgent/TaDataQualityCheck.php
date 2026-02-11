<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Model;

class TaDataQualityCheck extends Model
{
    protected $table = 'ta_data_quality_checks';

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'scope',
        'entity_id',
        'city_id',
        'lang',
        'check_name',
        'status',
        'message',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public const STATUS_PASS = 'pass';
    public const STATUS_WARN = 'warn';
    public const STATUS_FAIL = 'fail';

    public const SCOPES = [
        'blocks',
        'apartments',
        'block_detail',
        'apartment_detail',
        'directories',
        'unit_measurements',
    ];
}
