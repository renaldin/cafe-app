<?php

namespace App\Services;

use App\Models\InvoiceCounter;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nomor invoice/antrean/pesanan harian, aman konkurensi (row lock).
 * Wajib dipanggil di dalam DB transaction.
 */
class NumberService
{
    private function counter(): InvoiceCounter
    {
        $date = now()->toDateString();
        DB::table('invoice_counters')->insertOrIgnore(['date' => $date, 'last_invoice' => 0, 'last_queue' => 0, 'last_order' => 0]);

        return InvoiceCounter::query()->whereKey($date)->lockForUpdate()->firstOrFail();
    }

    public function nextInvoice(): string
    {
        $c = $this->counter();
        $n = $c->last_invoice + 1;
        DB::table('invoice_counters')->where('date', $c->date)->update(['last_invoice' => $n]);
        $prefix = Str::upper(Settings::get('invoice_prefix', 'CF')) ?: 'CF';

        return sprintf('%s-%s-%04d', $prefix, now()->format('Ymd'), $n);
    }

    public function nextQueue(): int
    {
        $c = $this->counter();
        $n = $c->last_queue + 1;
        DB::table('invoice_counters')->where('date', $c->date)->update(['last_queue' => $n]);

        return $n;
    }

    public function nextOrder(): int
    {
        $c = $this->counter();
        $n = $c->last_order + 1;
        DB::table('invoice_counters')->where('date', $c->date)->update(['last_order' => $n]);

        return $n;
    }

    public static function trackingKey(): string
    {
        return Str::lower(Str::random(32));
    }

    public static function docNo(string $prefix, string $table, string $column): string
    {
        $base = $prefix.'-'.now()->format('Ymd').'-';
        $last = DB::table($table)->where($column, 'like', $base.'%')->orderByDesc($column)->value($column);
        $n = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $base.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
