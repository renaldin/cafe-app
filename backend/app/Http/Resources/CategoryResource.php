<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'station_id' => $this->station_id,
            'station' => $this->whenLoaded('station', fn () => $this->station?->only(['id', 'name', 'code'])),
            'sort' => $this->sort,
            'is_active' => $this->is_active,
            'menus_count' => $this->whenCounted('menus'),
        ];
    }
}
