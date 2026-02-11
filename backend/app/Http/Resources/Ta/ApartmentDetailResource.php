<?php

namespace App\Http\Resources\Ta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApartmentDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'apartment_id' => $this->apartment_id,
            'city_id' => $this->city_id,
            'lang' => $this->lang,
            'unified_payload' => $this->unified_payload,
            'prices_totals_payload' => $this->prices_totals_payload,
            'prices_graph_payload' => $this->prices_graph_payload,
            'fetched_at' => $this->fetched_at?->toIso8601String(),
        ];
    }
}
