<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Exceptions\BusinessException;
use App\Models\Bill;
use App\Models\HappyHour;
use App\Models\Menu;
use App\Models\OrderItem;
use App\Support\Money;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Semua perhitungan harga dilakukan di server.
 */
class PricingService
{
    public function __construct(private RecipeCostService $cost) {}

    /**
     * Validasi & hitung satu baris item dari input klien (hanya menu_id, qty, option_ids, note).
     * Mengembalikan array siap disimpan sebagai OrderItem + modifiers.
     */
    public function priceLine(Menu $menu, int $qty, array $optionIds, ?string $note, ?Carbon $at = null): array
    {
        if (! $menu->isOrderableAt($at)) {
            throw new BusinessException("Menu \"{$menu->name}\" sedang tidak tersedia.");
        }
        $maxQty = (int) Settings::get('max_qty_per_item', 20);
        if ($qty < 1 || $qty > $maxQty) {
            throw new BusinessException("Jumlah untuk \"{$menu->name}\" harus antara 1 dan {$maxQty}.");
        }

        $optionIds = array_values(array_unique(array_map('intval', $optionIds)));
        $groups = $menu->modifierGroups; // eager loaded with options
        $chosen = [];
        $validIds = [];
        foreach ($groups as $group) {
            $opts = $group->options->keyBy('id');
            $picked = array_values(array_filter($optionIds, fn ($id) => $opts->has($id)));
            $count = count($picked);
            $min = $group->is_required ? max(1, (int) $group->min_select) : (int) $group->min_select;
            $max = $group->selection === 'single' ? 1 : max(1, (int) $group->max_select ?: $opts->count());
            if ($count < $min) {
                throw new BusinessException("Pilih opsi \"{$group->name}\" untuk \"{$menu->name}\".");
            }
            if ($count > $max) {
                throw new BusinessException("Opsi \"{$group->name}\" maksimal {$max} pilihan.");
            }
            foreach ($picked as $id) {
                $opt = $opts->get($id);
                if (! $opt->is_available) {
                    throw new BusinessException("Opsi \"{$opt->name}\" sedang tidak tersedia.");
                }
                $chosen[] = [
                    'modifier_option_id' => $opt->id,
                    'group_name' => $group->name,
                    'option_name' => $opt->name,
                    'price_delta' => (int) $opt->price_delta,
                ];
                $validIds[] = $id;
            }
        }
        $invalid = array_diff($optionIds, $validIds);
        if ($invalid) {
            throw new BusinessException("Opsi yang dipilih untuk \"{$menu->name}\" tidak valid.");
        }

        $modsTotal = array_sum(array_column($chosen, 'price_delta'));
        $unit = (int) $menu->price;
        $lineUnit = max(0, $unit + $modsTotal);

        return [
            'item' => [
                'menu_id' => $menu->id,
                'station_id' => $menu->effectiveStationId(),
                'menu_name' => $menu->name,
                'unit_price' => $unit,
                'modifiers_total' => $modsTotal,
                'qty' => $qty,
                'line_total' => $lineUnit * $qty,
                'cost_snapshot' => $this->cost->unitCost($menu, $validIds),
                'note' => $note ? mb_substr(strip_tags($note), 0, 200) : null,
                'status' => OrderItemStatus::New,
            ],
            'modifiers' => $chosen,
        ];
    }

    /** Hitung ulang seluruh total bill dari item aktif. */
    public function recalculate(Bill $bill): Bill
    {
        $items = $bill->items()->where('status', '!=', OrderItemStatus::Cancelled->value)->with('menu:id,category_id')->get();

        $subtotal = (int) $items->sum('line_total');
        $cost = (int) $items->sum(fn (OrderItem $i) => $i->cost_snapshot * $i->qty);
        $promo = min($subtotal, $this->happyHourDiscount($items));
        $base = $subtotal - $promo;

        // Diskon manual
        $discount = 0;
        if ($bill->discount_type === 'percent') {
            $discount = (int) round($base * min(100, $bill->discount_value) / 100);
        } elseif ($bill->discount_type === 'fixed') {
            $discount = (int) $bill->discount_value;
        }
        $discount = min($discount, $base);
        $base -= $discount;

        // Voucher
        $voucherAmt = 0;
        if ($bill->voucher_id && $bill->voucher) {
            $v = $bill->voucher;
            if ($subtotal >= $v->min_spend) {
                $voucherAmt = $v->type === 'percent' ? (int) round($base * min(100, $v->value) / 100) : (int) $v->value;
                if ($v->max_discount) {
                    $voucherAmt = min($voucherAmt, $v->max_discount);
                }
            }
        }
        $voucherAmt = min($voucherAmt, $base);
        $base -= $voucherAmt;

        // Poin
        $pointsAmt = min((int) $bill->points_redeemed * (int) Settings::get('loyalty_point_value', 100), $base);
        $base -= $pointsAmt;

        $service = Settings::get('service_enabled') && $bill->type->value === 'dine_in'
            ? (int) round($base * (float) Settings::get('service_percent', 0) / 100) : 0;
        $tax = Settings::get('tax_enabled') ? (int) round(($base + $service) * (float) Settings::get('tax_percent', 0) / 100) : 0;
        $gross = $base + $service + $tax + (int) $bill->delivery_fee;
        $rounded = Money::round($gross, (int) Settings::get('rounding', 0), (string) Settings::get('rounding_mode', 'nearest'));

        $bill->fill([
            'subtotal' => $subtotal,
            'promo_amount' => $promo,
            'discount_amount' => $discount,
            'voucher_amount' => $voucherAmt,
            'points_amount' => $pointsAmt,
            'service_charge' => $service,
            'tax' => $tax,
            'rounding' => $rounded - $gross,
            'total' => max(0, $rounded),
            'cost_total' => $cost,
        ]);
        $bill->save();

        return $bill;
    }

    /** Diskon happy hour per item, berdasarkan jam item dipesan. */
    public function happyHourDiscount(Collection $items): int
    {
        $rules = HappyHour::query()->where('is_active', true)->get();
        if ($rules->isEmpty()) {
            return 0;
        }
        $total = 0;
        foreach ($items as $item) {
            $at = $item->created_at ?? now();
            $best = 0;
            foreach ($rules as $r) {
                if (! in_array($at->dayOfWeekIso, array_map('intval', $r->days ?? []), true)) {
                    continue;
                }
                $t = $at->format('H:i:s');
                $start = strlen($r->start_time) === 5 ? $r->start_time.':00' : $r->start_time;
                $end = strlen($r->end_time) === 5 ? $r->end_time.':59' : $r->end_time;
                if ($t < $start || $t > $end) {
                    continue;
                }
                if ($r->category_ids && ! in_array((int) $item->menu?->category_id, array_map('intval', $r->category_ids), true)) {
                    continue;
                }
                $d = $r->type === 'percent' ? (int) round($item->line_total * min(100, $r->value) / 100) : min($item->line_total, $r->value * $item->qty);
                $best = max($best, $d);
            }
            $total += $best;
        }

        return $total;
    }
}
