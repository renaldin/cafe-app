<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\ModifierOptionRecipe;

/** Hitung HPP & kebutuhan bahan dari resep. */
class RecipeCostService
{
    /** Kebutuhan bahan per porsi: [ingredient_id => qty] */
    public function consumption(Menu $menu, array $optionIds = []): array
    {
        $need = [];
        if ($menu->is_bundle) {
            $menu->loadMissing('bundleItems.menu.recipes');
            foreach ($menu->bundleItems as $bi) {
                if (! $bi->menu) {
                    continue;
                }
                foreach ($this->consumption($bi->menu) as $ing => $q) {
                    $need[$ing] = ($need[$ing] ?? 0) + $q * $bi->qty;
                }
            }
        }
        $menu->loadMissing('recipes');
        foreach ($menu->recipes as $r) {
            $need[$r->ingredient_id] = ($need[$r->ingredient_id] ?? 0) + (float) $r->qty;
        }
        if ($optionIds) {
            $rows = ModifierOptionRecipe::query()->whereIn('modifier_option_id', $optionIds)->get();
            foreach ($rows as $r) {
                $need[$r->ingredient_id] = ($need[$r->ingredient_id] ?? 0) + (float) $r->qty;
            }
        }

        return array_filter($need, fn ($q) => abs($q) > 0.0000001);
    }

    public function unitCost(Menu $menu, array $optionIds = []): int
    {
        $need = $this->consumption($menu, $optionIds);
        if (! $need) {
            return 0;
        }
        $costs = \App\Models\Ingredient::withTrashed()->whereIn('id', array_keys($need))->pluck('cost_per_unit', 'id');
        $sum = 0;
        foreach ($need as $id => $q) {
            $sum += max(0, $q) * (float) ($costs[$id] ?? 0);
        }

        return (int) round($sum);
    }

    public function refreshMenuCost(Menu $menu): int
    {
        $cost = $this->unitCost($menu);
        $menu->forceFill(['cost_cache' => $cost])->saveQuietly();

        return $cost;
    }
}
