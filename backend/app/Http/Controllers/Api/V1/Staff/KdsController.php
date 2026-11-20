<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\OrderItemStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Station;
use App\Services\OrderService;
use App\Support\Polling;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KdsController extends Controller
{
    public function index(Request $request)
    {
        $station = $request->query('station'); // code: kitchen|bar|all
        $stationId = $station && $station !== 'all' ? Station::where('code', $station)->value('id') : null;

        $base = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('status', 'confirmed'))
            ->when($stationId, fn ($q) => $q->where('station_id', $stationId));

        $version = Polling::version((clone $base)->max('order_items.updated_at'), Order::max('updated_at'));
        if (Polling::unchanged($request->query('since'), $version)) {
            return $this->ok(['changed' => false], null, ['version' => $version]);
        }

        $items = (clone $base)->whereIn('status', ['new', 'processing', 'ready'])
            ->with(['modifiers', 'order.bill.table'])
            ->orderBy('created_at')->limit(300)->get();

        $cards = $items->groupBy('order_id')->map(function ($group) use ($request) {
            $order = $group->first()->order;
            $bill = $order->bill;

            return [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'label' => $bill->label(),
                'type' => $bill->type->value,
                'customer_name' => $bill->customer_name,
                'scheduled_at' => $bill->scheduled_at?->toIso8601String(),
                'note' => $order->note,
                'created_at' => $order->confirmed_at?->toIso8601String() ?? $order->created_at->toIso8601String(),
                'status' => OrderService::aggregateStatus($group),
                'items' => OrderItemResource::collection($group)->resolve($request),
            ];
        })->values();

        return $this->ok([
            'changed' => true,
            'cards' => $cards,
            'latest_order_id' => (int) Order::where('status', 'confirmed')->max('id'),
            'warn_minutes' => (int) Settings::get('kds_warn_minutes', 5),
            'danger_minutes' => (int) Settings::get('kds_danger_minutes', 10),
            'stations' => Station::orderBy('id')->get(['id', 'name', 'code']),
        ], null, ['version' => $version]);
    }

    public function updateItem(Request $request, OrderItem $item, OrderService $orders)
    {
        $data = $request->validate(['status' => ['required', 'in:new,processing,ready,served']]);
        $orders->transitionItem($item->load('order'), OrderItemStatus::from($data['status']), $request->user());

        return $this->ok(OrderItemResource::make($item->fresh('modifiers')), 'Status diperbarui.');
    }

    /** Ubah semua item pesanan (pada stasiun tertentu) sekaligus */
    public function updateOrder(Request $request, Order $order, OrderService $orders)
    {
        $data = $request->validate([
            'status' => ['required', 'in:processing,ready,served'],
            'station' => ['nullable', 'string'],
        ]);
        $to = OrderItemStatus::from($data['status']);
        $stationId = ! empty($data['station']) && $data['station'] !== 'all' ? Station::where('code', $data['station'])->value('id') : null;
        $changed = 0;
        DB::transaction(function () use ($order, $to, $stationId, $orders, $request, &$changed) {
            $items = $order->items()->when($stationId, fn ($q) => $q->where('station_id', $stationId))
                ->whereIn('status', ['new', 'processing', 'ready'])->lockForUpdate()->get();
            foreach ($items as $item) {
                if ($item->status->canTransitionTo($to)) {
                    $item->setRelation('order', $order);
                    $orders->transitionItem($item, $to, $request->user());
                    $changed++;
                }
            }
        });
        if ($changed === 0) {
            throw new BusinessException('Tidak ada item yang bisa diubah ke status tersebut.');
        }

        return $this->ok(['changed' => $changed], 'Status pesanan diperbarui.');
    }
}
