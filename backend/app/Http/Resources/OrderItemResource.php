<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staff = (bool) $request->user();

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'bill_id' => $this->bill_id,
            'menu_id' => $this->menu_id,
            'station_id' => $this->station_id,
            'name' => $this->menu_name,
            'unit_price' => $this->unit_price,
            'modifiers_total' => $this->modifiers_total,
            'qty' => $this->qty,
            'line_total' => $this->line_total,
            'note' => $this->note,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'modifiers' => $this->whenLoaded('modifiers', fn () => $this->modifiers->map(fn ($m) => [
                'group' => $m->group_name, 'name' => $m->option_name, 'price_delta' => $m->price_delta,
            ])),
            'cancel_reason' => $this->when($staff, $this->cancel_reason),
            'created_at' => $this->created_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'served_at' => $this->served_at?->toIso8601String(),
        ];
    }
}
