<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\OrderType;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicOrderRequest;
use App\Http\Resources\BillResource;
use App\Http\Resources\MenuResource;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Table;
use App\Models\TableCall;
use App\Payments\GatewayManager;
use App\Services\BillService;
use App\Services\ImageService;
use App\Services\MenuCatalog;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\Polling;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    public function store()
    {
        $s = Settings::all();
        $public = array_intersect_key($s, array_flip(Settings::publicKeys()));
        $public['cafe_logo'] = ImageService::url($s['cafe_logo'] ?? null);
        $public['payment_qris_image'] = ImageService::url($s['payment_qris_image'] ?? null);

        return $this->ok($public + [
            'is_open' => Settings::isStoreOpen(),
            'closed_message' => $s['store_closed_message'],
            'online_payment' => GatewayManager::enabled() && ! empty($s['allow_online_payment']),
            'online_pay_first' => GatewayManager::enabled() && ! empty($s['online_pay_first']),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function menu(Request $request)
    {
        $version = MenuCatalog::version();
        $etag = '"menu-'.$version.'"';
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }
        $data = Cache::remember('menu_catalog_'.$version, 600, function () use ($request) {
            $cats = Category::query()->where('is_active', true)->orderBy('sort')->orderBy('name')
                ->with(['menus' => fn ($q) => $q->where('is_active', true)->orderBy('sort')->orderBy('name')
                    ->with(['modifierGroups.options' => fn ($o) => $o->orderBy('sort')])])
                ->get();

            return $cats->filter(fn ($c) => $c->menus->isNotEmpty())->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'menus' => MenuResource::collection($c->menus)->resolve($request),
            ])->values()->all();
        });

        return $this->ok($data, null, ['version' => $version])->header('ETag', $etag)->header('Cache-Control', 'no-cache');
    }

    private function findTable(string $token): Table
    {
        $table = Table::query()->where('qr_token', $token)->where('is_active', true)->first();
        if (! $table) {
            throw new BusinessException('QR meja tidak valid atau sudah diganti. Minta bantuan pelayan.', 404);
        }

        return $table;
    }

    public function table(string $token)
    {
        $table = $this->findTable($token);
        $bill = $table->openBill;

        return $this->ok([
            'code' => $table->code,
            'name' => $table->name,
            'area' => $table->area?->name,
            'open_bill' => $bill ? ['tracking_key' => $bill->tracking_key, 'invoice_no' => $bill->invoice_no, 'total' => $bill->total] : null,
        ]);
    }

    public function placeOrder(PublicOrderRequest $request, OrderService $orders)
    {
        $data = $request->validated();
        $payload = [
            'items' => $data['items'],
            'customer_name' => isset($data['customer_name']) ? strip_tags($data['customer_name']) : null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'note' => $data['note'] ?? null,
            'client_session' => $data['client_session'] ?? null,
        ];

        if (! empty($data['table_token'])) {
            if (! Settings::isStoreOpen()) {
                throw new BusinessException(Settings::get('store_closed_message', 'Maaf, kami sedang tutup.'), 423);
            }
            $table = $this->findTable($data['table_token']);
            $payload['table_id'] = $table->id;
            $payload['type'] = OrderType::DineIn->value;
            $payload['source'] = 'qr';
        } else {
            // ---------- Pesan online: takeaway / delivery, bisa pre-order ----------
            $mode = ($data['mode'] ?? 'takeaway') === 'delivery' ? OrderType::Delivery : OrderType::Takeaway;
            if ($mode === OrderType::Takeaway && ! Settings::get('takeaway_enabled', true)) {
                throw new BusinessException('Pemesanan takeaway online sedang tidak tersedia.');
            }
            if ($mode === OrderType::Delivery && ! Settings::get('delivery_enabled', false)) {
                throw new BusinessException('Layanan pesan antar sedang tidak tersedia.');
            }

            $scheduled = $this->resolveSchedule($data['scheduled_at'] ?? null);
            $payload['scheduled_at'] = $scheduled;
            $payload['type'] = $mode->value;
            $payload['source'] = 'link';
            $payload['requires_payment'] = Settings::get('online_pay_first', false) && GatewayManager::enabled();

            if ($mode === OrderType::Delivery) {
                $payload['delivery_address'] = strip_tags((string) ($data['delivery_address'] ?? ''));
                $payload['delivery_fee'] = (int) Settings::get('delivery_fee', 0);
                $payload['min_subtotal'] = (int) Settings::get('delivery_min_order', 0);
            }
        }

        $order = $orders->place($payload, null, $request->ip());
        $bill = $order->bill;

        $message = match (true) {
            $order->requires_payment => 'Pesanan tercatat. Selesaikan pembayaran agar pesanan diproses.',
            $order->status->value === 'pending_confirmation' => 'Pesanan terkirim dan menunggu konfirmasi pelayan.',
            $bill->scheduled_at !== null => 'Pre-order berhasil untuk '.$bill->scheduled_at->translatedFormat('d M H:i').'.',
            default => 'Pesanan berhasil dikirim ke dapur.',
        };

        return $this->created([
            'tracking_key' => $bill->tracking_key,
            'invoice_no' => $bill->invoice_no,
            'order_no' => $order->order_no,
            'queue_no' => $bill->queue_no,
            'status' => $order->status->value,
            'requires_payment' => $order->requires_payment,
            'scheduled_at' => $bill->scheduled_at?->toIso8601String(),
            'total' => $bill->total,
        ], $message);
    }

    /**
     * Validasi jadwal pre-order. Tanpa jadwal: toko harus buka sekarang.
     * Dengan jadwal: minimal 15 menit lagi, maksimal N hari, dan di dalam jam buka.
     */
    private function resolveSchedule(?string $value): ?Carbon
    {
        if (! $value) {
            if (! Settings::isStoreOpen()) {
                $msg = Settings::get('preorder_enabled', true) ? ' Anda tetap bisa pre-order untuk jam buka berikutnya.' : '';
                throw new BusinessException(Settings::get('store_closed_message', 'Maaf, kami sedang tutup.').$msg, 423);
            }

            return null;
        }
        if (! Settings::get('preorder_enabled', true)) {
            throw new BusinessException('Pre-order sedang tidak tersedia.');
        }
        if (Settings::get('store_closed')) {
            throw new BusinessException(Settings::get('store_closed_message', 'Maaf, kami sedang tutup.'), 423);
        }
        $at = Carbon::parse($value)->setTimezone(config('app.timezone'))->seconds(0);
        $lead = (int) Settings::get('preorder_lead_minutes', 15);
        if ($at->lt(now()->addMinutes($lead))) {
            throw new BusinessException("Jadwal pre-order minimal {$lead} menit dari sekarang.");
        }
        $maxDays = (int) Settings::get('preorder_max_days', 3);
        if ($at->gt(now()->addDays($maxDays)->endOfDay())) {
            throw new BusinessException("Pre-order maksimal {$maxDays} hari ke depan.");
        }
        if (! Settings::isOpenAt($at)) {
            throw new BusinessException('Jadwal yang dipilih di luar jam operasional.');
        }

        return $at;
    }

    private function findBill(string $key): Bill
    {
        $bill = Bill::query()->where('tracking_key', $key)->first();
        if (! $bill) {
            throw new BusinessException('Pesanan tidak ditemukan.', 404);
        }
        if ($bill->status->value === 'merged' && $bill->merged_into_id) {
            $bill = Bill::findOrFail($bill->merged_into_id);
        }

        return $bill;
    }

    public function track(Request $request, string $key)
    {
        $bill = $this->findBill($key);
        $version = Polling::version(
            $bill->updated_at?->format('Y-m-d H:i:s'),
            $bill->items()->max('updated_at'),
            $bill->orders()->max('updated_at'),
            $bill->payments()->max('updated_at'),
        );
        if (Polling::unchanged($request->query('since'), $version)) {
            return $this->ok(['changed' => false], null, ['version' => $version]);
        }
        $bill->load(['table', 'orders' => fn ($q) => $q->orderBy('id'), 'orders.items.modifiers', 'payments' => fn ($q) => $q->latest()]);
        $pendingCall = $bill->table_id ? TableCall::where('table_id', $bill->table_id)->where('status', 'open')->pluck('type') : collect();

        return $this->ok([
            'changed' => true,
            'bill' => BillResource::make($bill)->resolve($request),
            'progress' => OrderService::aggregateStatus($bill->items()->get()),
            'open_calls' => $pendingCall->values(),
            'online_payment' => GatewayManager::enabled() && Settings::get('allow_online_payment'),
            'table_token' => null,
        ], null, ['version' => $version]);
    }

    public function call(Request $request, string $token)
    {
        $data = $request->validate(['type' => ['required', 'in:waiter,bill']]);
        $table = $this->findTable($token);
        $bill = $table->openBill;
        $exists = TableCall::where('table_id', $table->id)->where('type', $data['type'])->where('status', 'open')->exists();
        if (! $exists) {
            TableCall::create(['table_id' => $table->id, 'bill_id' => $bill?->id, 'type' => $data['type'], 'status' => 'open']);
        }
        if ($data['type'] === 'bill' && $bill && $bill->status->value === 'open') {
            app(BillService::class)->requestBill($bill);
        }

        return $this->ok(null, $data['type'] === 'bill' ? 'Permintaan bill dikirim. Mohon tunggu sebentar.' : 'Pelayan akan segera datang.');
    }

    public function payOnline(Request $request, string $key)
    {
        $gateway = GatewayManager::active();
        if (! $gateway || ! Settings::get('allow_online_payment')) {
            throw new BusinessException('Pembayaran online tidak tersedia. Silakan bayar di kasir.');
        }
        $data = $request->validate(['return_url' => ['nullable', 'url', 'max:500']]);
        $bill = $this->findBill($key);

        $payment = DB::transaction(function () use ($bill) {
            $bill = Bill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if (! $bill->isOpen()) {
                throw new BusinessException('Bill sudah dibayar atau ditutup.');
            }
            if ($bill->orders()->where('status', 'pending_confirmation')->where('requires_payment', false)->exists()) {
                throw new BusinessException('Pesanan masih menunggu konfirmasi pelayan.');
            }
            $due = $bill->balanceDue();
            if ($due <= 0) {
                throw new BusinessException('Tidak ada tagihan yang perlu dibayar.');
            }
            // pakai ulang transaksi pending yang masih berlaku dengan nominal sama
            $existing = Payment::where('bill_id', $bill->id)->where('method', 'gateway')->where('status', 'pending')
                ->where('amount', $due)->where('expires_at', '>', now()->addMinutes(2))->whereNotNull('gateway_url')->latest()->first();
            if ($existing) {
                return $existing;
            }
            Payment::where('bill_id', $bill->id)->where('method', 'gateway')->where('status', 'pending')->update(['status' => 'expired']);

            return Payment::create(['bill_id' => $bill->id, 'method' => 'gateway', 'amount' => $due, 'status' => 'pending']);
        });

        if (! $payment->gateway_url) {
            $return = $data['return_url'] ?? rtrim((string) config('app.frontend_url'), '/').'/lacak/?k='.$bill->tracking_key;
            $res = $gateway->create($bill, $payment, $return);
            $payment->update([
                'gateway' => $gateway->name(),
                'gateway_ref' => $res['ref'],
                'gateway_url' => $res['url'],
                'gateway_qr' => $res['qr'],
                'expires_at' => $res['expires_at'],
            ]);
        }

        return $this->ok(['payment_id' => $payment->id, 'url' => $payment->gateway_url, 'amount' => $payment->amount, 'expires_at' => $payment->expires_at?->toIso8601String()]);
    }

    public function paymentStatus(string $key, int $paymentId, PaymentService $payments)
    {
        $bill = $this->findBill($key);
        $payment = Payment::where('bill_id', $bill->id)->whereKey($paymentId)->firstOrFail();
        if ($payment->status === 'pending' && $payment->gateway) {
            // cek ke gateway paling cepat tiap 10 detik per pembayaran
            $lock = 'pay_check_'.$payment->id;
            if (Cache::add($lock, 1, 10)) {
                $gw = GatewayManager::byName($payment->gateway);
                $status = $gw?->checkStatus($payment);
                if ($status === 'paid') {
                    $payments->confirmGatewayPayment($payment);
                } elseif (in_array($status, ['expired', 'failed'], true)) {
                    $payment->update(['status' => $status]);
                }
                $payment->refresh();
            }
        }

        return $this->ok(['status' => $payment->status, 'bill_status' => $bill->fresh()->status->value]);
    }

    public function webhook(Request $request, string $gateway, PaymentService $payments)
    {
        $gw = GatewayManager::byName($gateway);
        if (! $gw) {
            return response()->json(['message' => 'Gateway tidak aktif'], 404);
        }
        $parsed = $gw->parseWebhook($request);
        if (! $parsed) {
            return response()->json(['message' => 'Signature tidak valid'], 403);
        }
        $payment = Payment::where('gateway_ref', $parsed['ref'])->first();
        if (! $payment) {
            return response()->json(['message' => 'OK (tidak dikenal)']);
        }
        if ($parsed['status'] === 'paid') {
            $payments->confirmGatewayPayment($payment, $parsed['payload']);
        } elseif (in_array($parsed['status'], ['expired', 'failed'], true) && $payment->status === 'pending') {
            $payment->update(['status' => $parsed['status'], 'gateway_payload' => $parsed['payload']]);
        }

        return response()->json(['message' => 'OK']);
    }
}
