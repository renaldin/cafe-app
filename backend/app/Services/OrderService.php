<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\TableStatus;
use App\Exceptions\BusinessException;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\User;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private PricingService $pricing,
        private NumberService $numbers,
        private StockService $stock,
    ) {}

    /**
     * Buat pesanan.
     * $data: type, table_id|table (Table), items[menu_id,qty,option_ids,note], customer_name, customer_phone,
     *        delivery_address, delivery_fee, note, source, bill_id (tambah ke bill tertentu)
     */
    public function place(array $data, ?User $staff = null, ?string $ip = null): Order
    {
        $lines = $data['items'] ?? [];
        $maxLines = (int) Settings::get('max_lines_per_order', 30);
        if (count($lines) < 1) {
            throw new BusinessException('Keranjang masih kosong.');
        }
        if (count($lines) > $maxLines) {
            throw new BusinessException("Maksimal {$maxLines} item per pesanan.");
        }

        return DB::transaction(function () use ($data, $lines, $staff, $ip) {
            $type = OrderType::from($data['type'] ?? 'dine_in');
            $table = null;
            if (! empty($data['table_id'])) {
                $table = Table::query()->whereKey($data['table_id'])->where('is_active', true)->lockForUpdate()->first();
                if (! $table) {
                    throw new BusinessException('Meja tidak ditemukan atau tidak aktif.');
                }
                $type = OrderType::DineIn;
            } elseif ($type === OrderType::DineIn && empty($data['bill_id'])) {
                throw new BusinessException('Pilih meja untuk pesanan dine-in.');
            }

            // Hitung semua baris di server (abaikan harga dari klien)
            $menuIds = array_unique(array_map(fn ($l) => (int) $l['menu_id'], $lines));
            $menus = Menu::query()->whereIn('id', $menuIds)
                ->with(['category', 'modifierGroups.options', 'recipes', 'bundleItems.menu.recipes'])
                ->get()->keyBy('id');
            $priced = [];
            foreach ($lines as $l) {
                $menu = $menus->get((int) $l['menu_id']);
                if (! $menu || ! $menu->category || ! $menu->category->is_active) {
                    throw new BusinessException('Ada menu yang tidak tersedia. Silakan muat ulang menu.');
                }
                // pre-order: ketersediaan jam menu dicek pada jam yang dijadwalkan
                $priced[] = $this->pricing->priceLine($menu, (int) $l['qty'], (array) ($l['option_ids'] ?? []), $l['note'] ?? null, $data['scheduled_at'] ?? null);
            }

            // Minimal belanja (mis. delivery online), dihitung dari harga server
            if (! empty($data['min_subtotal'])) {
                $sub = array_sum(array_map(fn ($p) => $p['item']['line_total'], $priced));
                if ($sub < (int) $data['min_subtotal']) {
                    throw new BusinessException('Minimal pemesanan '.\App\Support\Money::format((int) $data['min_subtotal']).' untuk layanan ini.');
                }
            }

            // Bill: tambah ke bill terbuka meja, bill tertentu, atau buat baru
            $bill = null;
            if (! empty($data['bill_id'])) {
                $bill = Bill::query()->whereKey($data['bill_id'])->lockForUpdate()->firstOrFail();
                if (! $bill->isOpen()) {
                    throw new BusinessException('Bill sudah ditutup.');
                }
            } elseif ($table) {
                $bill = Bill::query()->where('table_id', $table->id)->whereIn('status', ['open', 'awaiting_payment'])
                    ->whereNull('parent_bill_id')->lockForUpdate()->latest('id')->first();
            }

            $customer = $this->resolveCustomer($data['customer_phone'] ?? null, $data['customer_name'] ?? null);

            if (! $bill) {
                $bill = Bill::create([
                    'invoice_no' => $this->numbers->nextInvoice(),
                    'tracking_key' => NumberService::trackingKey(),
                    'table_id' => $table?->id,
                    'customer_id' => $customer?->id,
                    'type' => $type,
                    'queue_no' => $type === OrderType::DineIn ? null : $this->numbers->nextQueue(),
                    'customer_name' => $data['customer_name'] ?? $customer?->name,
                    'customer_phone' => $data['customer_phone'] ?? null,
                    'delivery_address' => $type === OrderType::Delivery ? ($data['delivery_address'] ?? null) : null,
                    'delivery_fee' => $type === OrderType::Delivery ? (int) ($data['delivery_fee'] ?? 0) : 0,
                    'status' => BillStatus::Open,
                    'guest_count' => $data['guest_count'] ?? null,
                    'note' => $data['bill_note'] ?? null,
                    'opened_by' => $staff?->id,
                    'opened_at' => now(),
                    'scheduled_at' => $data['scheduled_at'] ?? null,
                ]);
            } else {
                $patch = [];
                if (! $bill->customer_id && $customer) {
                    $patch['customer_id'] = $customer->id;
                }
                if (! $bill->customer_name && ! empty($data['customer_name'])) {
                    $patch['customer_name'] = $data['customer_name'];
                }
                if (! $bill->customer_phone && ! empty($data['customer_phone'])) {
                    $patch['customer_phone'] = $data['customer_phone'];
                }
                if ($bill->status === BillStatus::AwaitingPayment) {
                    $patch['status'] = BillStatus::Open;
                    $patch['bill_requested'] = false;
                }
                if ($patch) {
                    $bill->update($patch);
                }
            }

            $source = $data['source'] ?? ($staff ? 'staff' : ($table ? 'qr' : 'link'));
            // requires_payment: pesanan online ditahan sampai dibayar (gateway / konfirmasi kasir)
            $requiresPayment = ! $staff && ! empty($data['requires_payment']);
            $needConfirm = ! $staff && (Settings::get('require_confirmation', false) || $requiresPayment);

            $order = Order::create([
                'bill_id' => $bill->id,
                'order_no' => $this->numbers->nextOrder(),
                'source' => $source,
                'status' => $needConfirm ? OrderStatus::PendingConfirmation : OrderStatus::Confirmed,
                'requires_payment' => $requiresPayment,
                'created_by' => $staff?->id,
                'confirmed_by' => $needConfirm ? null : $staff?->id,
                'confirmed_at' => $needConfirm ? null : now(),
                'client_session' => isset($data['client_session']) ? substr((string) $data['client_session'], 0, 64) : null,
                'ip' => $ip,
                'note' => isset($data['note']) ? mb_substr(strip_tags((string) $data['note']), 0, 200) : null,
            ]);

            foreach ($priced as $p) {
                $item = $order->items()->create($p['item'] + ['bill_id' => $bill->id]);
                if ($p['modifiers']) {
                    $item->modifiers()->createMany($p['modifiers']);
                }
            }

            $this->pricing->recalculate($bill->fresh(['voucher']));

            if ($table && $table->status !== TableStatus::Occupied) {
                $table->update(['status' => TableStatus::Occupied]);
            }

            return $order->load(['items.modifiers', 'bill']);
        });
    }

    public function resolveCustomer(?string $phone, ?string $name): ?Customer
    {
        $phone = self::normalizePhone($phone);
        if (! $phone) {
            return null;
        }
        $customer = Customer::withTrashed()->where('phone', $phone)->first();
        if (! $customer) {
            $customer = Customer::create(['phone' => $phone, 'name' => $name ?: 'Pelanggan']);
        } elseif ($customer->trashed()) {
            $customer->restore();
        }

        return $customer;
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $p = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($p, '0')) {
            $p = '62'.substr($p, 1);
        } elseif (str_starts_with($p, '8')) {
            $p = '62'.$p;
        }

        return strlen($p) >= 9 ? $p : null;
    }

    public function confirm(Order $order, User $user): Order
    {
        if ($order->status !== OrderStatus::PendingConfirmation) {
            throw new BusinessException('Pesanan ini tidak menunggu konfirmasi.');
        }
        $order->update(['status' => OrderStatus::Confirmed, 'confirmed_by' => $user->id, 'confirmed_at' => now()]);
        $order->items()->update(['updated_at' => now()]);

        return $order;
    }

    public function reject(Order $order, User $user, string $reason): Order
    {
        if ($order->status !== OrderStatus::PendingConfirmation) {
            throw new BusinessException('Pesanan ini tidak menunggu konfirmasi.');
        }

        return DB::transaction(function () use ($order, $user, $reason) {
            $order->update(['status' => OrderStatus::Rejected, 'confirmed_by' => $user->id, 'note' => $reason]);
            $order->items()->update(['status' => OrderItemStatus::Cancelled->value, 'cancel_reason' => 'Ditolak: '.$reason, 'cancelled_by' => $user->id, 'updated_at' => now()]);
            $bill = $order->bill()->lockForUpdate()->first();
            $this->pricing->recalculate($bill);
            app(BillService::class)->closeIfEmpty($bill, $user);
            Activity::log('order.reject', $order, "Menolak pesanan #{$order->order_no}", null, null, $reason);

            return $order;
        });
    }

    /** Ubah status item (KDS) dengan validasi state machine. */
    public function transitionItem(OrderItem $item, OrderItemStatus $to, ?User $user = null): OrderItem
    {
        $from = $item->status;
        if ($from === $to) {
            return $item;
        }
        if ($to === OrderItemStatus::Cancelled) {
            throw new BusinessException('Gunakan fitur hapus item untuk membatalkan.');
        }
        if (! $from->canTransitionTo($to)) {
            throw new BusinessException("Status tidak bisa diubah dari {$from->label()} ke {$to->label()}.");
        }
        if ($item->order->status !== OrderStatus::Confirmed) {
            throw new BusinessException('Pesanan belum dikonfirmasi.');
        }
        $patch = ['status' => $to];
        if ($to === OrderItemStatus::Processing && ! $item->started_at) {
            $patch['started_at'] = now();
        }
        if ($to === OrderItemStatus::Ready) {
            $patch['ready_at'] = now();
            $patch['started_at'] = $item->started_at ?? now();
        }
        if ($to === OrderItemStatus::Served) {
            $patch['served_at'] = now();
        }
        if ($to === OrderItemStatus::New) {
            $patch['started_at'] = null;
        }
        $item->update($patch);

        if ($to === OrderItemStatus::Served && Settings::get('stock_deduct_on') === 'served') {
            $this->stock->deductItems(collect([$item]));
        }

        return $item;
    }

    /** Batalkan/hapus item dari bill */
    public function cancelItem(OrderItem $item, User $user, ?string $reason): OrderItem
    {
        return DB::transaction(function () use ($item, $user, $reason) {
            $bill = Bill::query()->whereKey($item->bill_id)->lockForUpdate()->firstOrFail();
            if (! $bill->isOpen()) {
                throw new BusinessException('Bill sudah ditutup, gunakan refund.');
            }
            if ($bill->paid_amount > 0) {
                throw new BusinessException('Bill sudah dibayar sebagian, item tidak bisa dihapus.');
            }
            $item->refresh();
            if ($item->status === OrderItemStatus::Cancelled) {
                throw new BusinessException('Item sudah dibatalkan.');
            }
            $inKitchen = $item->order->status === OrderStatus::Confirmed;
            if ($inKitchen) {
                if (! $user->can('order.cancel_item')) {
                    throw new BusinessException('Item sudah masuk dapur. Anda tidak memiliki izin untuk menghapusnya.', 403);
                }
                if (! $reason || mb_strlen(trim($reason)) < 3) {
                    throw new BusinessException('Alasan wajib diisi untuk menghapus item yang sudah masuk dapur.');
                }
            }
            $before = ['status' => $item->status->value, 'qty' => $item->qty, 'line_total' => $item->line_total];
            if ($item->stock_deducted) {
                $this->stock->restoreItems(collect([$item]), 'Item dibatalkan');
            }
            $item->update(['status' => OrderItemStatus::Cancelled, 'cancel_reason' => $reason, 'cancelled_by' => $user->id]);
            $this->pricing->recalculate($bill->load('voucher'));
            Activity::log('order.cancel_item', $item, "Hapus item {$item->menu_name} x{$item->qty} dari {$bill->invoice_no}", $before, ['status' => 'cancelled'], $reason);
            app(BillService::class)->closeIfEmpty($bill, $user);

            return $item;
        });
    }

    /** Status agregat pesanan dari item-itemnya */
    public static function aggregateStatus(iterable $items): string
    {
        $statuses = collect($items)->map(fn ($i) => $i->status->value)->reject(fn ($s) => $s === 'cancelled')->values();
        if ($statuses->isEmpty()) {
            return 'cancelled';
        }
        if ($statuses->every(fn ($s) => $s === 'served')) {
            return 'served';
        }
        if ($statuses->every(fn ($s) => in_array($s, ['ready', 'served'], true))) {
            return 'ready';
        }
        if ($statuses->contains(fn ($s) => in_array($s, ['processing', 'ready', 'served'], true))) {
            return 'processing';
        }

        return 'new';
    }
}
