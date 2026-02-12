<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaBlock extends Model
{
    use HasFactory;

    protected $table = 'ta_blocks';

    protected $fillable = [
        'block_id',
        'guid',
        'title',
        'city_id',
        'lang',
        'kind',
        'status',
        'min_price',
        'max_price',
        'deadline',
        'developer_name',
        'lat',
        'lng',
        'raw',
        'normalized',
        'payload_hash',
        'fetched_at',
    ];

    protected $casts = [
        'min_price' => 'integer',
        'max_price' => 'integer',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'normalized' => 'array',
        'fetched_at' => 'datetime',
    ];

    /**
     * Get raw JSON as array
     */
    public function getRawDataAttribute(): ?array
    {
        if (! $this->raw) {
            return null;
        }
        $decoded = json_decode($this->raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Set raw data from array
     */
    public function setRawAttribute($value): void
    {
        $this->attributes['raw'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Get location as array [lat, lng]
     */
    public function getLocationAttribute(): ?array
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }
        return [(float) $this->lat, (float) $this->lng];
    }
}
