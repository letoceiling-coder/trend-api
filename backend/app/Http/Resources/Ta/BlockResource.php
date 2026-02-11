<?php

namespace App\Http\Resources\Ta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->block_id,
            'block_id' => $this->block_id,
            'guid' => $this->guid,
            'title' => $this->title,
            'city_id' => $this->city_id,
            'lang' => $this->lang,
            'kind' => $this->kind,
            'status' => $this->status,
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
            'deadline' => $this->deadline,
            'developer_name' => $this->developer_name,
            'location' => $this->location,
            'fetched_at' => $this->fetched_at?->toIso8601String(),
        ];
    }
}
