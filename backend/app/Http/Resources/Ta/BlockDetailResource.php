<?php

namespace App\Http\Resources\Ta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'block_id' => $this->block_id,
            'city_id' => $this->city_id,
            'lang' => $this->lang,
            'unified_payload' => $this->unified_payload,
            'advantages_payload' => $this->advantages_payload,
            'nearby_places_payload' => $this->nearby_places_payload,
            'bank_payload' => $this->bank_payload,
            'geo_buildings_payload' => $this->geo_buildings_payload,
            'apartments_min_price_payload' => $this->apartments_min_price_payload,
            'fetched_at' => $this->fetched_at?->toIso8601String(),
        ];
    }
}
