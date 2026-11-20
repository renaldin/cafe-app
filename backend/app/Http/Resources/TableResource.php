<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bill = $this->relationLoaded('openBill') ? $this->openBill : null;

        return [
            'id' => $this->id,
            'area_id' => $this->area_id,
            'area' => $this->whenLoaded('area', fn () => $this->area?->only(['id', 'name'])),
            'code' => $this->code,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'qr_token' => $this->when($request->user()?->can('table.view'), $this->qr_token),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'pos_x' => $this->pos_x,
            'pos_y' => $this->pos_y,
            'is_active' => $this->is_active,
            'open_bill' => $bill ? [
                'id' => $bill->id,
                'invoice_no' => $bill->invoice_no,
                'total' => $bill->total,
                'status' => $bill->status->value,
                'opened_at' => $bill->opened_at?->toIso8601String(),
                'bill_requested' => $bill->bill_requested,
            ] : null,
        ];
    }
}
