<?php

namespace App\Http\Resources\Ta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->apartment_id,
            'apartment_id' => $this->apartment_id,
            'block_id' => $this->block_id,
            'guid' => $this->guid,
            'title' => $this->title,
            'rooms' => $this->rooms,
            'area_total' => $this->area_total,
            'floor' => $this->floor,
            'price' => $this->price,
            'status' => $this->status,
            'city_id' => $this->city_id,
            'lang' => $this->lang,
            'fetched_at' => $this->fetched_at?->toIso8601String(),
        ];
    }
}
