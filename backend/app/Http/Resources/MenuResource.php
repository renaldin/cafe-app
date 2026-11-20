<?php

namespace App\Http\Resources;

use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staff = (bool) $request->user();

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->only(['id', 'name'])),
            'station_id' => $this->station_id,
            'sku' => $this->when($staff, $this->sku),
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => ImageService::url($this->image),
            'price' => $this->price,
            'cost' => $this->when($staff, $this->cost_cache),
            'labels' => $this->labels ?? [],
            'is_available' => $this->is_available,
            'is_active' => $this->is_active,
            'is_bundle' => $this->is_bundle,
            'available_from' => $this->available_from ? substr($this->available_from, 0, 5) : null,
            'available_until' => $this->available_until ? substr($this->available_until, 0, 5) : null,
            'sort' => $this->sort,
            'modifier_groups' => ModifierGroupResource::collection($this->whenLoaded('modifierGroups')),
            'recipes' => $this->whenLoaded('recipes', fn () => $this->recipes->map(fn ($r) => [
                'ingredient_id' => $r->ingredient_id,
                'qty' => $r->qty,
                'ingredient' => $r->ingredient ? ['id' => $r->ingredient->id, 'name' => $r->ingredient->name, 'unit' => $r->ingredient->unit?->symbol, 'cost_per_unit' => $r->ingredient->cost_per_unit] : null,
            ])),
            'bundle_items' => $this->whenLoaded('bundleItems', fn () => $this->bundleItems->map(fn ($b) => ['menu_id' => $b->menu_id, 'qty' => $b->qty, 'name' => $b->menu?->name])),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
