<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staff = (bool) $request->user();

        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'tracking_key' => $this->when($staff, $this->tracking_key),
            'parent_bill_id' => $this->parent_bill_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'queue_no' => $this->queue_no,
            'label' => $this->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'table' => $this->whenLoaded('table', fn () => $this->table ? $this->table->only(['id', 'code', 'name']) : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? $this->customer->only(['id', 'name', 'phone', 'points']) : null),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'delivery_address' => $this->delivery_address,
            'delivery_fee' => $this->delivery_fee,
            'guest_count' => $this->guest_count,
            'note' => $this->note,
            'subtotal' => $this->subtotal,
            'promo_amount' => $this->promo_amount,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'discount_amount' => $this->discount_amount,
            'voucher' => $this->whenLoaded('voucher', fn () => $this->voucher ? $this->voucher->only(['id', 'code', 'name']) : null),
            'voucher_amount' => $this->voucher_amount,
            'points_redeemed' => $this->points_redeemed,
            'points_amount' => $this->points_amount,
            'service_charge' => $this->service_charge,
            'tax' => $this->tax,
            'rounding' => $this->rounding,
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'balance_due' => $this->balanceDue(),
            'change_amount' => $this->change_amount,
            'cost_total' => $this->when($staff && $request->user()?->can('report.finance'), $this->cost_total),
            'bill_requested' => $this->bill_requested,
            'void_reason' => $this->when($staff, $this->void_reason),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'opened_by' => $this->whenLoaded('opener', fn () => $this->opener?->name),
            'closed_by' => $this->whenLoaded('closer', fn () => $this->closer?->name),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id, 'method' => $p->method->value, 'method_label' => $p->method->label(), 'amount' => $p->amount,
                'received' => $p->received, 'change' => $p->change, 'reference' => $p->reference, 'status' => $p->status,
                'paid_at' => $p->paid_at?->toIso8601String(),
            ])),
            'refunds' => $this->whenLoaded('refunds', fn () => $this->refunds->map(fn ($r) => $r->only(['id', 'amount', 'method', 'reason', 'restock']) + ['created_at' => $r->created_at?->toIso8601String()])),
            'children' => $this->whenLoaded('children', fn () => $this->children->map(fn ($c) => ['id' => $c->id, 'invoice_no' => $c->invoice_no, 'status' => $c->status->value, 'total' => $c->total])),
        ];
    }
}
