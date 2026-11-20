<?php

namespace App\Http\Resources;

use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bill_id' => $this->bill_id,
            'order_no' => $this->order_no,
            'source' => $this->source,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'requires_payment' => (bool) $this->requires_payment,
            'progress' => $this->relationLoaded('items') ? OrderService::aggregateStatus($this->items) : null,
            'note' => $this->note,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'bill' => $this->whenLoaded('bill', fn () => [
                'id' => $this->bill->id,
                'invoice_no' => $this->bill->invoice_no,
                'type' => $this->bill->type->value,
                'queue_no' => $this->bill->queue_no,
                'table' => $this->bill->relationLoaded('table') && $this->bill->table ? $this->bill->table->only(['id', 'code', 'name']) : null,
                'customer_name' => $this->bill->customer_name,
                'scheduled_at' => $this->bill->scheduled_at?->toIso8601String(),
            ]),
        ];
    }
}
