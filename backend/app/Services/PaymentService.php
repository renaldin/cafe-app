<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Enums\TableStatus;
use App\Exceptions\BusinessException;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private PricingService $pricing,
        private StockService $stock,
        private LoyaltyService $loyalty,
        private BillService $bills,
    ) {}

    /**
     * Proses pembayaran (bisa split metode & bisa parsial untuk split per orang).
     * $payments = [[method, amount, received?, reference?]]
     */
    public function pay(Bill $bill, array $payments, User $user): Bill
    {
        $shift = $user->openShift();
        if (! $shift) {
            throw new BusinessException('Anda belum membuka shift.', 409);
        }

        return DB::transaction(function () use ($bill, $payments, $user, $shift) {
            // Kunci baris bill: cegah pembayaran ganda
            $bill = Bill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if (! $bill->isOpen()) {
                throw new BusinessException('Bill sudah dibayar atau ditutup.', 409);
            }
            // Pesanan online "bayar dulu" boleh dibayar; pesanan anti-prank harus dikonfirmasi dulu
            if ($bill->orders()->where('status', 'pending_confirmation')->where('requires_payment', false)->exists()) {
                throw new BusinessException('Masih ada pesanan yang menunggu konfirmasi.');
            }
            $bill = $this->pricing->recalculate($bill->load('voucher'));
            if ($bill->total <= 0 && $bill->items()->where('status', '!=', 'cancelled')->doesntExist()) {
                throw new BusinessException('Bill tidak memiliki item.');
            }
            $due = $bill->balanceDue();

            $nonCash = 0;
            $cashReceived = 0;
            foreach ($payments as $p) {
                $m = PaymentMethod::from($p['method']);
                if ($m === PaymentMethod::Gateway) {
                    throw new BusinessException('Metode pembayaran tidak valid.');
                }
                if ($m === PaymentMethod::Cash) {
                    $cashReceived += (int) ($p['received'] ?? $p['amount']);
                } else {
                    $nonCash += (int) $p['amount'];
                }
            }
            if ($nonCash > $due) {
                throw new BusinessException('Pembayaran non-tunai melebihi sisa tagihan.');
            }
            $cashNeeded = $due - $nonCash;
            $cashApplied = min($cashReceived, $cashNeeded);
            $change = max(0, $cashReceived - $cashNeeded);
            $applied = $nonCash + $cashApplied;
            if ($applied <= 0) {
                throw new BusinessException('Nominal pembayaran belum diisi.');
            }

            foreach ($payments as $p) {
                $m = PaymentMethod::from($p['method']);
                if ($m === PaymentMethod::Cash) {
                    if ($cashReceived <= 0) {
                        continue;
                    }
                    Payment::create([
                        'bill_id' => $bill->id, 'shift_id' => $shift->id, 'method' => $m,
                        'amount' => $cashApplied, 'received' => $cashReceived, 'change' => $change,
                        'status' => 'paid', 'paid_at' => now(), 'created_by' => $user->id,
                    ]);
                    $cashReceived = 0; // gabungkan semua baris tunai menjadi satu
                } else {
                    if ((int) $p['amount'] <= 0) {
                        continue;
                    }
                    Payment::create([
                        'bill_id' => $bill->id, 'shift_id' => $shift->id, 'method' => $m,
                        'amount' => (int) $p['amount'], 'received' => (int) $p['amount'], 'change' => 0,
                        'reference' => isset($p['reference']) ? mb_substr((string) $p['reference'], 0, 100) : null,
                        'status' => 'paid', 'paid_at' => now(), 'created_by' => $user->id,
                    ]);
                }
            }

            $bill->paid_amount += $applied;
            $bill->change_amount = $change;
            $bill->shift_id = $bill->shift_id ?? $shift->id;
            $bill->save();

            if ($bill->paid_amount >= $bill->total) {
                $this->finalize($bill, $user);
            }
            Activity::log('payment.create', $bill, "Pembayaran {$bill->invoice_no}", null, ['applied' => $applied, 'change' => $change, 'methods' => array_column($payments, 'method')], null, $user->id);

            return $bill->fresh(['payments']);
        });
    }

    /** Tandai bill lunas: stok, poin, voucher, meja. Dipanggil di dalam transaksi + lock. */
    public function finalize(Bill $bill, ?User $user = null): void
    {
        $bill->update([
            'status' => BillStatus::Paid,
            'paid_at' => now(),
            'closed_by' => $user?->id ?? $bill->closed_by,
        ]);
        $this->stock->deductItems($bill->items()->where('status', '!=', 'cancelled')->get());
        $this->loyalty->settle($bill);
        if ($bill->voucher_id && $bill->voucher_amount > 0) {
            Voucher::whereKey($bill->voucher_id)->increment('used_count');
            VoucherUsage::create(['voucher_id' => $bill->voucher_id, 'bill_id' => $bill->id, 'customer_id' => $bill->customer_id, 'amount' => $bill->voucher_amount]);
        }
        if ($bill->table_id) {
            $this->bills->releaseTable($bill->table_id, TableStatus::NeedsCleaning);
        }
        // Pesanan online yang menunggu pembayaran otomatis diteruskan ke dapur
        $held = $bill->orders()->where('status', 'pending_confirmation')->where('requires_payment', true)->get();
        foreach ($held as $order) {
            $order->update(['status' => \App\Enums\OrderStatus::Confirmed, 'confirmed_at' => now(), 'confirmed_by' => $user?->id]);
            $order->items()->update(['updated_at' => now()]);
        }
    }

    /** Konfirmasi pembayaran gateway (webhook/polling). Idempoten. */
    public function confirmGatewayPayment(Payment $payment, array $payload = []): void
    {
        DB::transaction(function () use ($payment, $payload) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid') {
                return;
            }
            $bill = Bill::query()->whereKey($payment->bill_id)->lockForUpdate()->firstOrFail();
            $payment->update(['status' => 'paid', 'paid_at' => now(), 'gateway_payload' => $payload]);
            if (! $bill->isOpen()) {
                // Bill sudah ditutup (mis. sudah dibayar tunai) -> tandai untuk refund manual
                Activity::log('payment.gateway_orphan', $bill, "Pembayaran online diterima untuk bill yang sudah ditutup: {$bill->invoice_no}", null, ['payment_id' => $payment->id]);

                return;
            }
            $bill->paid_amount += $payment->amount;
            $bill->save();
            if ($bill->paid_amount >= $bill->total) {
                $this->finalize($bill);
            }
        });
    }
}
