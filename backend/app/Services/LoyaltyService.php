<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Support\Settings;

class LoyaltyService
{
    /** Saat bill lunas: potong poin ditukar + tambah poin baru + statistik pelanggan. */
    public function settle(Bill $bill): void
    {
        if (! $bill->customer_id) {
            return;
        }
        $customer = Customer::query()->whereKey($bill->customer_id)->lockForUpdate()->first();
        if (! $customer) {
            return;
        }
        if ($bill->points_redeemed > 0) {
            $customer->points = max(0, $customer->points - $bill->points_redeemed);
            PointTransaction::create(['customer_id' => $customer->id, 'bill_id' => $bill->id, 'points' => -$bill->points_redeemed, 'type' => 'redeem', 'note' => 'Tukar poin '.$bill->invoice_no]);
        }
        $earn = 0;
        if (Settings::get('loyalty_enabled')) {
            $per = max(1, (int) Settings::get('loyalty_spend_per_point', 10000));
            $earn = intdiv(max(0, $bill->total - $bill->tax - $bill->service_charge), $per);
        }
        if ($earn > 0) {
            $customer->points += $earn;
            PointTransaction::create(['customer_id' => $customer->id, 'bill_id' => $bill->id, 'points' => $earn, 'type' => 'earn', 'note' => 'Belanja '.$bill->invoice_no]);
        }
        $customer->total_spent += $bill->total;
        $customer->visit_count += 1;
        $customer->last_visit_at = now();
        $customer->save();
    }

    public function reverse(Bill $bill): void
    {
        if (! $bill->customer_id) {
            return;
        }
        $customer = Customer::query()->whereKey($bill->customer_id)->lockForUpdate()->first();
        if (! $customer) {
            return;
        }
        $net = (int) PointTransaction::where('bill_id', $bill->id)->sum('points');
        if ($net !== 0) {
            $customer->points = max(0, $customer->points - $net);
            PointTransaction::create(['customer_id' => $customer->id, 'bill_id' => $bill->id, 'points' => -$net, 'type' => 'reverse', 'note' => 'Refund '.$bill->invoice_no]);
        }
        $customer->total_spent = max(0, $customer->total_spent - $bill->total);
        $customer->save();
    }
}
