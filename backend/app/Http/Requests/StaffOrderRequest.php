<?php

namespace App\Http\Requests;

class StaffOrderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required_without:bill_id', 'in:dine_in,takeaway,delivery'],
            'table_id' => ['nullable', 'integer', 'exists:tables,id', 'required_if:type,dine_in'],
            'bill_id' => ['nullable', 'integer', 'exists:bills,id'],
            'customer_name' => ['nullable', 'string', 'max:60'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'delivery_address' => ['nullable', 'string', 'max:500', 'required_if:type,delivery'],
            'delivery_fee' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'note' => ['nullable', 'string', 'max:200'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.menu_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.option_ids' => ['nullable', 'array'],
            'items.*.option_ids.*' => ['integer'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
