<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\OrderItem;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(private RecipeCostService $recipes) {}

    /**
     * Mutasi stok atomik (row lock). qty positif = masuk, negatif = keluar.
     */
    public function move(int $ingredientId, float $qty, string $type, ?string $refType = null, ?int $refId = null, ?string $reason = null, ?float $unitCost = null, ?int $userId = null): StockMovement
    {
        return DB::transaction(function () use ($ingredientId, $qty, $type, $refType, $refId, $reason, $unitCost, $userId) {
            /** @var Ingredient $ing */
            $ing = Ingredient::withTrashed()->whereKey($ingredientId)->lockForUpdate()->firstOrFail();
            $before = (float) $ing->stock;
            $after = round($before + $qty, 3);

            if ($type === 'purchase' && $qty > 0 && $unitCost !== null) {
                // Harga rata-rata tertimbang
                $oldValue = max(0, $before) * (float) $ing->cost_per_unit;
                $newValue = $qty * $unitCost;
                $ing->cost_per_unit = $after > 0 ? round(($oldValue + $newValue) / max($after, 0.001), 4) : $unitCost;
            }
            $ing->stock = $after;
            $ing->save();

            return StockMovement::create([
                'ingredient_id' => $ing->id,
                'type' => $type,
                'qty' => $qty,
                'stock_after' => $after,
                'unit_cost' => $unitCost ?? (float) $ing->cost_per_unit,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'reason' => $reason,
                'user_id' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    /** Set stok ke nilai tertentu (opname/penyesuaian absolut) */
    public function setTo(int $ingredientId, float $counted, string $type, ?string $refType, ?int $refId, ?string $reason): ?StockMovement
    {
        $ing = Ingredient::withTrashed()->findOrFail($ingredientId);
        $diff = round($counted - (float) $ing->stock, 3);
        if (abs($diff) < 0.0005) {
            return null;
        }

        return $this->move($ingredientId, $diff, $type, $refType, $refId, $reason);
    }

    /** Kurangi stok untuk item yang belum dipotong. */
    public function deductItems(Collection $items): void
    {
        $items = $items->filter(fn (OrderItem $i) => ! $i->stock_deducted && $i->status->value !== 'cancelled' && $i->menu_id);
        if ($items->isEmpty()) {
            return;
        }
        $items->loadMissing(['menu' => fn ($q) => $q->withTrashed()->with(['recipes', 'bundleItems.menu.recipes']), 'modifiers']);
        $touched = [];
        foreach ($items as $item) {
            if (! $item->menu) {
                continue;
            }
            $optIds = $item->modifiers->pluck('modifier_option_id')->filter()->all();
            foreach ($this->recipes->consumption($item->menu, $optIds) as $ingId => $q) {
                $this->move((int) $ingId, -round($q * $item->qty, 3), 'sale', 'order_item', $item->id, 'Penjualan '.$item->menu_name);
                $touched[] = (int) $ingId;
            }
            $item->forceFill(['stock_deducted' => true])->saveQuietly();
        }
        $this->checkAutoSoldOut(array_unique($touched));
    }

    /** Kembalikan stok item yang sudah dipotong (void/refund). */
    public function restoreItems(Collection $items, string $reason): void
    {
        $items->loadMissing(['menu' => fn ($q) => $q->withTrashed()->with(['recipes', 'bundleItems.menu.recipes']), 'modifiers']);
        foreach ($items as $item) {
            if (! $item->stock_deducted || ! $item->menu) {
                continue;
            }
            $optIds = $item->modifiers->pluck('modifier_option_id')->filter()->all();
            foreach ($this->recipes->consumption($item->menu, $optIds) as $ingId => $q) {
                $this->move((int) $ingId, round($q * $item->qty, 3), 'return', 'order_item', $item->id, $reason);
            }
            $item->forceFill(['stock_deducted' => false])->saveQuietly();
        }
    }

    /** Tandai menu habis bila bahan utama habis. */
    public function checkAutoSoldOut(array $ingredientIds): void
    {
        if (! Settings::get('auto_soldout', true) || ! $ingredientIds) {
            return;
        }
        $empty = Ingredient::query()->whereIn('id', $ingredientIds)->where('is_key', true)->where('stock', '<=', 0)->pluck('id');
        if ($empty->isEmpty()) {
            return;
        }
        $menuIds = Recipe::query()->whereIn('ingredient_id', $empty)->pluck('menu_id')->unique();
        $n = Menu::query()->whereIn('id', $menuIds)->where('is_available', true)->update(['is_available' => false, 'updated_at' => now()]);
        if ($n > 0) {
            MenuCatalog::bump();
        }
    }

    public function assertPositive(float $qty): void
    {
        if ($qty <= 0) {
            throw new BusinessException('Jumlah harus lebih dari 0.');
        }
    }
}
