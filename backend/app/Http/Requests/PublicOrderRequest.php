<?php

namespace App\Http\Requests;

class PublicOrderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'table_token' => ['nullable', 'string', 'size:48', 'alpha_num'],
            'mode' => ['nullable', 'in:dine_in,takeaway,delivery'],
            'customer_name' => ['nullable', 'string', 'max:60', 'required_if:mode,takeaway,delivery'],
            'customer_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{8,20}$/', 'required_if:mode,takeaway,delivery'],
            'delivery_address' => ['nullable', 'string', 'min:10', 'max:500', 'required_if:mode,delivery'],
            'scheduled_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:200'],
            'client_session' => ['nullable', 'string', 'max:64'],
            'website' => ['prohibited'], // honeypot: harus kosong
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.menu_id' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.option_ids' => ['nullable', 'array', 'max:20'],
            'items.*.option_ids.*' => ['integer', 'min:1'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'website.prohibited' => 'Permintaan ditolak.',
            'delivery_address.required_if' => 'Alamat pengantaran wajib diisi.',
            'delivery_address.min' => 'Alamat pengantaran terlalu singkat, lengkapi patokan/nomor rumah.',
        ];
    }
}
