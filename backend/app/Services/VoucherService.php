<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Support\Money;
use Illuminate\Support\Str;

class VoucherService
{
    public function validate(string $code, int $subtotal, ?int $customerId = null): Voucher
    {
        $v = Voucher::query()->where('code', Str::upper(trim($code)))->first();
        if (! $v || ! $v->is_active) {
            throw new BusinessException('Kode voucher tidak valid.');
        }
        if ($v->starts_at && now()->lt($v->starts_at)) {
            throw new BusinessException('Voucher belum berlaku.');
        }
        if ($v->ends_at && now()->gt($v->ends_at)) {
            throw new BusinessException('Voucher sudah kedaluwarsa.');
        }
        if ($v->usage_limit !== null && $v->used_count >= $v->usage_limit) {
            throw new BusinessException('Kuota voucher sudah habis.');
        }
        if ($subtotal < $v->min_spend) {
            throw new BusinessException('Minimal belanja '.Money::format($v->min_spend).' untuk voucher ini.');
        }
        if ($v->per_customer_limit && $customerId) {
            $used = VoucherUsage::where('voucher_id', $v->id)->where('customer_id', $customerId)->count();
            if ($used >= $v->per_customer_limit) {
                throw new BusinessException('Pelanggan sudah mencapai batas pemakaian voucher ini.');
            }
        }

        return $v;
    }
}
