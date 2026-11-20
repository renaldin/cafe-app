<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReasonRequest;
use App\Http\Requests\StaffOrderRequest;
use App\Http\Resources\BillResource;
use App\Http\Resources\OrderResource;
use App\Models\Bill;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableCall;
use App\Services\OrderService;
use App\Support\Polling;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /** Pesanan manual oleh pelayan/kasir (juga untuk tambah item ke bill) */
    public function store(StaffOrderRequest $request, OrderService $orders)
    {
        $order = $orders->place($request->validated(), $request->user(), $request->ip());

        return $this->created(OrderResource::make($order->load('items.modifiers', 'bill.table')), 'Pesanan dibuat.');
    }

    public function index(Request $request)
    {
        $q = Order::query()->with(['items.modifiers', 'bill.table', 'creator'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('created_at', $d))
            ->latest('id');

        return $this->paginated($q->paginate($this->perPage()), OrderResource::class);
    }

    /**
     * Feed kasir/pelayan (polling): bill aktif, pesanan menunggu konfirmasi, panggilan meja.
     */
    public function feed(Request $request)
    {
        $version = Polling::version(
            Bill::query()->whereIn('status', ['open', 'awaiting_payment'])->max('updated_at'),
            Bill::query()->max('updated_at'),
            Order::query()->max('updated_at'),
            OrderItem::query()->max('updated_at'),
            TableCall::query()->max('updated_at'),
        );
        if (Polling::unchanged($request->query('since'), $version)) {
            return $this->ok(['changed' => false], null, ['version' => $version]);
        }
        $bills = Bill::query()->whereIn('status', ['open', 'awaiting_payment'])
            ->with(['table', 'items' => fn ($q) => $q->where('status', '!=', 'cancelled')->select('id', 'bill_id', 'status', 'qty', 'menu_name'), 'orders:id,bill_id,status,requires_payment'])
            ->orderBy('opened_at')->limit(200)->get();
        $pending = Order::query()->where('status', 'pending_confirmation')->with(['items.modifiers', 'bill.table'])->oldest()->limit(50)->get();
        $calls = TableCall::query()->where('status', 'open')->with('table:id,code,name')->latest()->limit(50)->get();

        return $this->ok([
            'changed' => true,
            'bills' => $bills->map(fn (Bill $b) => [
                'id' => $b->id,
                'invoice_no' => $b->invoice_no,
                'label' => $b->label(),
                'type' => $b->type->value,
                'status' => $b->status->value,
                'table' => $b->table?->only(['id', 'code', 'name']),
                'customer_name' => $b->customer_name,
                'total' => $b->total,
                'paid_amount' => $b->paid_amount,
                'item_count' => (int) $b->items->sum('qty'),
                'progress' => OrderService::aggregateStatus($b->items),
                'pending_confirmation' => $b->orders->contains(fn ($o) => $o->status->value === 'pending_confirmation'),
                'bill_requested' => $b->bill_requested,
                'opened_at' => $b->opened_at?->toIso8601String(),
                'scheduled_at' => $b->scheduled_at?->toIso8601String(),
                'awaiting_online_payment' => $b->orders->contains(fn ($o) => $o->status->value === 'pending_confirmation' && $o->requires_payment),
            ]),
            'pending_orders' => OrderResource::collection($pending)->resolve($request),
            'calls' => $calls->map(fn ($c) => ['id' => $c->id, 'type' => $c->type, 'table' => $c->table?->only(['id', 'code', 'name']), 'created_at' => $c->created_at->toIso8601String()]),
            'latest_order_id' => (int) Order::max('id'),
        ], null, ['version' => $version]);
    }

    public function confirm(Request $request, Order $order, OrderService $orders)
    {
        $orders->confirm($order, $request->user());

        return $this->ok(null, 'Pesanan dikonfirmasi dan dikirim ke dapur.');
    }

    public function reject(ReasonRequest $request, Order $order, OrderService $orders)
    {
        $orders->reject($order, $request->user(), $request->validated('reason'));

        return $this->ok(null, 'Pesanan ditolak.');
    }

    public function cancelItem(Request $request, OrderItem $item, OrderService $orders)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $orders->cancelItem($item, $request->user(), $data['reason'] ?? null);
        $bill = $item->bill()->with(['items.modifiers', 'table', 'payments'])->first();

        return $this->ok(BillResource::make($bill), 'Item dihapus dari bill.');
    }
}
