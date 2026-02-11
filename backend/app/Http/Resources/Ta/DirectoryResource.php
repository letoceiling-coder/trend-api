<?php

namespace App\Http\Resources\Ta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DirectoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'city_id' => $this->city_id,
            'lang' => $this->lang,
            'payload' => $this->payload,
            'fetched_at' => $this->fetched_at?->toIso8601String(),
        ];
    }
}
