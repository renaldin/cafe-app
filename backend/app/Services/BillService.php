<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\OrderItemStatus;
use App\Enums\TableStatus;
use App\Exceptions\BusinessException;
use App\Models\Bill;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

class BillService
{
    public function __construct(
        private PricingService $pricing,
        private NumberService $numbers,
        private StockService $stock,
        private LoyaltyService $loyalty,
    ) {}

    public function lock(Bill $bill): Bill
    {
        return Bill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
    }

    private function assertOpen(Bill $bill): void
    {
        if (! $bill->isOpen()) {
            throw new BusinessException('Bill sudah ditutup.');
        }
    }

    public function requestBill(Bill $bill): Bill
    {
        return DB::transaction(function () use ($bill) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);
            $bill->update(['status' => BillStatus::AwaitingPayment, 'bill_requested' => true]);
            if ($bill->table_id) {
                Table::whereKey($bill->table_id)->update(['status' => TableStatus::AwaitingPayment->value, 'updated_at' => now()]);
            }

            return $bill;
        });
    }

    public function setDiscount(Bill $bill, ?string $type, int $value, User $user, ?string $reason): Bill
    {
        return DB::transaction(function () use ($bill, $type, $value, $user, $reason) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);
            if ($type === 'percent' && $value > 100) {
                throw new BusinessException('Diskon persen maksimal 100%.');
            }
            $before = ['discount_type' => $bill->discount_type, 'discount_value' => $bill->discount_value];
            $bill->update(['discount_type' => $type, 'discount_value' => $type ? $value : 0]);
            $this->pricing->recalculate($bill->load('voucher'));
            Activity::log('bill.discount', $bill, "Diskon pada {$bill->invoice_no}", $before, ['discount_type' => $type, 'discount_value' => $value, 'discount_amount' => $bill->discount_amount], $reason, $user->id);

            return $bill;
        });
    }

    public function applyVoucher(Bill $bill, ?string $code): Bill
    {
        return DB::transaction(function () use ($bill, $code) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);
            if (! $code) {
                $bill->update(['voucher_id' => null]);

                return $this->pricing->recalculate($bill->fresh());
            }
            $voucher = app(VoucherService::class)->validate($code, $bill->subtotal, $bill->customer_id);
            $bill->update(['voucher_id' => $voucher->id]);
            $bill = $this->pricing->recalculate($bill->fresh(['voucher']));
            if ($bill->voucher_amount <= 0) {
                throw new BusinessException('Voucher tidak memberi potongan untuk bill ini.');
            }

            return $bill;
        });
    }

    public function redeemPoints(Bill $bill, int $points): Bill
    {
        return DB::transaction(function () use ($bill, $points) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);
            if ($points > 0) {
                if (! Settings::get('loyalty_enabled')) {
                    throw new BusinessException('Program poin tidak aktif.');
                }
                if (! $bill->customer_id) {
                    throw new BusinessException('Hubungkan pelanggan terlebih dahulu.');
                }
                $available = (int) $bill->customer->points;
                if ($points > $available) {
                    throw new BusinessException("Poin pelanggan hanya {$available}.");
                }
                if ($points < (int) Settings::get('loyalty_min_redeem', 0)) {
                    throw new BusinessException('Minimal penukaran '.Settings::get('loyalty_min_redeem').' poin.');
                }
            }
            $bill->update(['points_redeemed' => max(0, $points)]);

            return $this->pricing->recalculate($bill->fresh(['voucher']));
        });
    }

    /** Ubah ongkir/alamat/jadwal pesanan online (staf) */
    public function updateDelivery(Bill $bill, array $data, User $user): Bill
    {
        return DB::transaction(function () use ($bill, $data, $user) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);
            if ($bill->paid_amount > 0 && array_key_exists('delivery_fee', $data)) {
                throw new BusinessException('Ongkir tidak bisa diubah setelah ada pembayaran.');
            }
            $before = $bill->only(['delivery_fee', 'delivery_address', 'scheduled_at']);
            $bill->update(array_intersect_key($data, array_flip(['delivery_fee', 'delivery_address', 'scheduled_at'])));
            $bill = $this->pricing->recalculate($bill->fresh(['voucher']));
            Activity::log('bill.delivery', $bill, "Ubah pengantaran {$bill->invoice_no}", $before, $bill->only(['delivery_fee', 'delivery_address', 'scheduled_at']), null, $user->id);

            return $bill;
        });
    }

    public function attachCustomer(Bill $bill, ?int $customerId, ?string $name, ?string $phone): Bill
    {
        return DB::transaction(function () use ($bill, $customerId, $name, $phone) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);
            $customer = $customerId ? \App\Models\Customer::findOrFail($customerId) : app(OrderService::class)->resolveCustomer($phone, $name);
            $bill->update([
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name ?? $name,
                'customer_phone' => $customer?->phone ?? $phone,
                'points_redeemed' => 0,
            ]);

            return $this->pricing->recalculate($bill->fresh(['voucher']));
        });
    }

    /**
     * Pecah bill per item: $lines = [[item_id, qty]] dipindah ke bill baru.
     */
    public function splitByItems(Bill $bill, array $lines, User $user): Bill
    {
        return DB::transaction(function () use ($bill, $lines, $user) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);
            if ($bill->paid_amount > 0) {
                throw new BusinessException('Bill yang sudah dibayar sebagian tidak bisa dipecah per item.');
            }
            if (! $lines) {
                throw new BusinessException('Pilih item yang akan dipisah.');
            }
            $activeCount = $bill->items()->where('status', '!=', 'cancelled')->sum('qty');
            $moveCount = array_sum(array_map(fn ($l) => (int) $l['qty'], $lines));
            if ($moveCount >= $activeCount) {
                throw new BusinessException('Minimal satu item harus tersisa di bill asal.');
            }

            $child = Bill::create([
                'invoice_no' => $this->numbers->nextInvoice(),
                'tracking_key' => NumberService::trackingKey(),
                'parent_bill_id' => $bill->id,
                'table_id' => $bill->table_id,
                'customer_name' => $bill->customer_name,
                'type' => $bill->type,
                'queue_no' => $bill->queue_no,
                'status' => $bill->status,
                'opened_by' => $user->id,
                'opened_at' => now(),
            ]);

            foreach ($lines as $l) {
                /** @var OrderItem $item */
                $item = $bill->items()->whereKey($l['item_id'])->where('status', '!=', 'cancelled')->lockForUpdate()->first();
                $qty = (int) $l['qty'];
                if (! $item || $qty < 1 || $qty > $item->qty) {
                    throw new BusinessException('Item atau jumlah yang dipisah tidak valid.');
                }
                if ($qty === $item->qty) {
                    $item->update(['bill_id' => $child->id]);
                } else {
                    $unit = intdiv($item->line_total, $item->qty);
                    $new = $item->replicate(['id']);
                    $new->qty = $qty;
                    $new->line_total = $unit * $qty;
                    $new->bill_id = $child->id;
                    $new->save();
                    foreach ($item->modifiers as $m) {
                        $new->modifiers()->create($m->only(['modifier_option_id', 'group_name', 'option_name', 'price_delta']));
                    }
                    $item->update(['qty' => $item->qty - $qty, 'line_total' => $unit * ($item->qty - $qty)]);
                }
            }

            $this->pricing->recalculate($bill->fresh(['voucher']));
            $child = $this->pricing->recalculate($child->fresh());
            Activity::log('bill.split', $bill, "Pecah bill {$bill->invoice_no} -> {$child->invoice_no}", null, ['lines' => $lines], null, $user->id);

            return $child->load('items.modifiers');
        });
    }

    /** Gabung bill sumber ke bill tujuan */
    public function merge(Bill $target, array $sourceIds, User $user): Bill
    {
        return DB::transaction(function () use ($target, $sourceIds, $user) {
            $target = $this->lock($target);
            $this->assertOpen($target);
            $sources = Bill::query()->whereIn('id', $sourceIds)->where('id', '!=', $target->id)->lockForUpdate()->get();
            if ($sources->isEmpty()) {
                throw new BusinessException('Pilih bill yang akan digabung.');
            }
            foreach ($sources as $s) {
                if (! $s->isOpen() || $s->paid_amount > 0) {
                    throw new BusinessException("Bill {$s->invoice_no} tidak bisa digabung (sudah dibayar/ditutup).");
                }
                OrderItem::where('bill_id', $s->id)->update(['bill_id' => $target->id, 'updated_at' => now()]);
                Order::where('bill_id', $s->id)->update(['bill_id' => $target->id, 'updated_at' => now()]);
                $s->update(['status' => BillStatus::Merged, 'merged_into_id' => $target->id, 'total' => 0, 'subtotal' => 0]);
                if ($s->table_id && $s->table_id !== $target->table_id) {
                    $this->releaseTable($s->table_id, TableStatus::Empty);
                }
            }
            $target = $this->pricing->recalculate($target->fresh(['voucher']));
            Activity::log('bill.merge', $target, "Gabung bill ke {$target->invoice_no}", null, ['sources' => $sources->pluck('invoice_no')], null, $user->id);

            return $target;
        });
    }

    public function moveTable(Bill $bill, int $tableId, User $user): Bill
    {
        return DB::transaction(function () use ($bill, $tableId, $user) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);
            $to = Table::query()->whereKey($tableId)->where('is_active', true)->lockForUpdate()->firstOrFail();
            if ($to->id === $bill->table_id) {
                throw new BusinessException('Meja tujuan sama dengan meja saat ini.');
            }
            $busy = Bill::where('table_id', $to->id)->whereIn('status', ['open', 'awaiting_payment'])->exists();
            if ($busy) {
                throw new BusinessException("Meja {$to->code} sedang terisi. Gunakan fitur gabung bill.");
            }
            $fromId = $bill->table_id;
            $bill->update(['table_id' => $to->id, 'type' => 'dine_in']);
            // pindahkan juga bill anak (split) yang masih terbuka
            Bill::where('parent_bill_id', $bill->id)->whereIn('status', ['open', 'awaiting_payment'])->update(['table_id' => $to->id]);
            $to->update(['status' => $bill->status === BillStatus::AwaitingPayment ? TableStatus::AwaitingPayment : TableStatus::Occupied]);
            if ($fromId) {
                $this->releaseTable($fromId, TableStatus::NeedsCleaning);
            }
            Activity::log('bill.move_table', $bill, "Pindah {$bill->invoice_no} ke meja {$to->code}", ['table_id' => $fromId], ['table_id' => $to->id], null, $user->id);

            return $bill;
        });
    }

    /** Kosongkan meja jika tidak ada bill terbuka lain */
    public function releaseTable(int $tableId, TableStatus $status): void
    {
        $open = Bill::where('table_id', $tableId)->whereIn('status', ['open', 'awaiting_payment'])->exists();
        if (! $open) {
            Table::whereKey($tableId)->update(['status' => $status->value, 'updated_at' => now()]);
        }
    }

    /** Bill tanpa item aktif dan belum dibayar -> otomatis void */
    public function closeIfEmpty(Bill $bill, ?User $user = null): void
    {
        $hasActive = $bill->items()->where('status', '!=', 'cancelled')->exists();
        if (! $hasActive && $bill->paid_amount == 0 && $bill->isOpen()) {
            $bill->update(['status' => BillStatus::Void, 'void_reason' => 'Semua item dibatalkan', 'closed_by' => $user?->id]);
            if ($bill->table_id) {
                $this->releaseTable($bill->table_id, TableStatus::Empty);
            }
        }
    }

    public function void(Bill $bill, User $user, string $reason): Bill
    {
        return DB::transaction(function () use ($bill, $user, $reason) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);
            if ($bill->paid_amount > 0) {
                throw new BusinessException('Bill sudah menerima pembayaran. Gunakan refund.');
            }
            $items = $bill->items()->where('status', '!=', 'cancelled')->get();
            $this->stock->restoreItems($items, 'Void '.$bill->invoice_no);
            $bill->items()->where('status', '!=', 'cancelled')->update([
                'status' => OrderItemStatus::Cancelled->value, 'cancel_reason' => 'Void: '.$reason, 'cancelled_by' => $user->id, 'updated_at' => now(),
            ]);
            $before = ['status' => $bill->status->value, 'total' => $bill->total];
            $bill->update(['status' => BillStatus::Void, 'void_reason' => $reason, 'closed_by' => $user->id]);
            if ($bill->table_id) {
                $this->releaseTable($bill->table_id, TableStatus::Empty);
            }
            Activity::log('bill.void', $bill, "Void {$bill->invoice_no}", $before, ['status' => 'void'], $reason, $user->id);

            return $bill;
        });
    }

    public function refund(Bill $bill, User $user, int $amount, string $reason, bool $restock, string $method = 'cash'): Bill
    {
        return DB::transaction(function () use ($bill, $user, $amount, $reason, $restock, $method) {
            $bill = $this->lock($bill);
            if ($bill->status !== BillStatus::Paid) {
                throw new BusinessException('Hanya bill lunas yang bisa direfund.');
            }
            $refunded = (int) $bill->refunds()->sum('amount');
            $max = $bill->total - $refunded;
            if ($amount < 1 || $amount > $max) {
                throw new BusinessException('Nominal refund maksimal '.\App\Support\Money::format($max).'.');
            }
            $shift = $user->openShift();
            if ($method === 'cash' && ! $shift) {
                throw new BusinessException('Buka shift terlebih dahulu untuk refund tunai.', 409);
            }
            $bill->refunds()->create([
                'shift_id' => $shift?->id,
                'amount' => $amount,
                'method' => $method,
                'reason' => $reason,
                'restock' => $restock,
                'created_by' => $user->id,
            ]);
            if ($restock) {
                $this->stock->restoreItems($bill->items()->get(), 'Refund '.$bill->invoice_no);
            }
            $full = ($refunded + $amount) >= $bill->total;
            if ($full) {
                $bill->update(['status' => BillStatus::Refunded]);
                $this->loyalty->reverse($bill);
                if ($bill->voucher_id) {
                    Voucher::whereKey($bill->voucher_id)->where('used_count', '>', 0)->decrement('used_count');
                }
            } else {
                $bill->touch();
            }
            Activity::log('bill.refund', $bill, "Refund {$bill->invoice_no}", ['refunded' => $refunded], ['refunded' => $refunded + $amount, 'restock' => $restock], $reason, $user->id);

            return $bill;
        });
    }

    public function markCleaned(Table $table): Table
    {
        $open = Bill::where('table_id', $table->id)->whereIn('status', ['open', 'awaiting_payment'])->exists();
        if ($open) {
            throw new BusinessException('Meja masih memiliki bill terbuka.');
        }
        Bill::where('table_id', $table->id)->where('status', 'paid')->whereNull('cleaned_at')->update(['cleaned_at' => now()]);
        $table->update(['status' => TableStatus::Empty]);

        return $table;
    }
}
