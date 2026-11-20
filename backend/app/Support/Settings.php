<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan aplikasi (key-value di tabel settings), di-cache.
 */
class Settings
{
    public const CACHE_KEY = 'app_settings_v1';

    public static function defaults(): array
    {
        $hours = [];
        foreach (range(1, 7) as $d) {
            $hours[(string) $d] = ['open' => '08:00', 'close' => '22:00', 'closed' => false];
        }

        return [
            'cafe_name' => 'Kopi Senja',
            'cafe_logo' => null,
            'cafe_address' => 'Jl. Contoh No. 1, Jakarta',
            'cafe_phone' => '081234567890',
            'cafe_email' => null,
            'social_instagram' => null,
            'social_tiktok' => null,
            'social_website' => null,
            'opening_hours' => $hours,
            'enforce_hours' => false,
            'store_closed' => false,
            'store_closed_message' => 'Maaf, kami sedang tutup.',
            'tax_enabled' => true,
            'tax_percent' => 11,
            'service_enabled' => false,
            'service_percent' => 5,
            'rounding' => 0, // 0 = tanpa pembulatan, 100/500/1000
            'rounding_mode' => 'nearest', // nearest|up|down
            'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
            'receipt_paper' => '58',
            'invoice_prefix' => 'CF',
            'require_confirmation' => false,
            'stock_deduct_on' => 'paid', // paid|served
            'auto_soldout' => true,
            'loyalty_enabled' => true,
            'loyalty_spend_per_point' => 10000,
            'loyalty_point_value' => 100,
            'loyalty_min_redeem' => 50,
            'max_qty_per_item' => 20,
            'max_lines_per_order' => 30,
            'kds_warn_minutes' => 5,
            'kds_danger_minutes' => 10,
            'payment_qris_image' => null,
            'payment_bank_info' => 'BCA 1234567890 a.n. Kopi Senja',
            'allow_online_payment' => true,
            'takeaway_enabled' => true,
            // Pesan online (link toko)
            'delivery_enabled' => false,
            'delivery_fee' => 10000,
            'delivery_min_order' => 30000,
            'delivery_area_note' => 'Melayani area radius 5 km dari cafe.',
            'preorder_enabled' => true,
            'preorder_lead_minutes' => 15,
            'preorder_max_days' => 3,
            'online_pay_first' => false,
        ];
    }

    /** Kunci yang boleh dilihat publik */
    public static function publicKeys(): array
    {
        return [
            'cafe_name', 'cafe_logo', 'cafe_address', 'cafe_phone', 'social_instagram', 'social_tiktok', 'social_website',
            'opening_hours', 'tax_enabled', 'tax_percent', 'service_enabled', 'service_percent', 'max_qty_per_item',
            'max_lines_per_order', 'payment_bank_info', 'payment_qris_image', 'takeaway_enabled', 'require_confirmation',
            'enforce_hours', 'delivery_enabled', 'delivery_fee', 'delivery_min_order', 'delivery_area_note',
            'preorder_enabled', 'preorder_lead_minutes', 'preorder_max_days',
        ];
    }

    public static function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return Setting::query()->pluck('value', 'key')->toArray();
            } catch (\Throwable) {
                return [];
            }
        });

        return array_merge(self::defaults(), $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(array $values): void
    {
        $allowed = array_keys(self::defaults());
        foreach ($values as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Toko sedang buka? */
    public static function isStoreOpen(): bool
    {
        if (self::get('store_closed')) {
            return false;
        }

        return self::isOpenAt(now());
    }

    /** Apakah waktu tertentu berada di jam operasional (untuk pre-order) */
    public static function isOpenAt(\Carbon\CarbonInterface $at): bool
    {
        if (! self::get('enforce_hours')) {
            return true;
        }
        $day = (string) $at->dayOfWeekIso;
        $h = self::get('opening_hours')[$day] ?? null;
        if (! $h || ! empty($h['closed'])) {
            return false;
        }
        $t = $at->format('H:i');
        if ($h['open'] <= $h['close']) {
            return $t >= $h['open'] && $t < $h['close'];
        }

        return $t >= $h['open'] || $t < $h['close'];
    }
}
