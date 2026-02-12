<?php

namespace App\Models\Domain\TrendAgent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TaCity extends Model
{
    use HasFactory;

    protected $table = 'ta_cities';

    protected $fillable = ['key', 'city_id', 'name', 'base_url'];

    /**
     * Cities to sync (all regions). From ta_cities; if empty, fallback to default_city_id from config.
     *
     * @return Collection<int, array{city_id: string, key: string, name: string}>
     */
    public static function getRegionsToSync(): Collection
    {
        $rows = self::query()->orderBy('key')->get(['city_id', 'key', 'name']);
        if ($rows->isNotEmpty()) {
            return $rows->map(fn ($r) => [
                'city_id' => $r->city_id,
                'key' => $r->key,
                'name' => $r->name ?? $r->key,
            ]);
        }
        $defaultId = (string) config('trendagent.default_city_id', '');
        if ($defaultId !== '') {
            return collect([[
                'city_id' => $defaultId,
                'key' => 'default',
                'name' => 'Default (env)',
            ]]);
        }
        return collect();
    }
}
