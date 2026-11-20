<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit', fn () => $this->unit?->symbol),
            'stock' => round((float) $this->stock, 3),
            'min_stock' => round((float) $this->min_stock, 3),
            'cost_per_unit' => round((float) $this->cost_per_unit, 4),
            'stock_value' => (int) round(max(0, $this->stock) * $this->cost_per_unit),
            'is_key' => $this->is_key,
            'is_low' => $this->isLow(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
