<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'selection' => $this->selection,
            'is_required' => $this->is_required,
            'min_select' => $this->min_select,
            'max_select' => $this->max_select,
            'sort' => $this->pivot->sort ?? $this->sort,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'price_delta' => $o->price_delta,
                'is_available' => $o->is_available,
                'is_default' => $o->is_default,
                'sort' => $o->sort,
                'recipes' => $o->relationLoaded('recipes') ? $o->recipes->map(fn ($r) => ['ingredient_id' => $r->ingredient_id, 'qty' => $r->qty, 'ingredient' => $r->ingredient?->name]) : null,
            ])->values()),
            'menus_count' => $this->whenCounted('menus'),
        ];
    }
}
