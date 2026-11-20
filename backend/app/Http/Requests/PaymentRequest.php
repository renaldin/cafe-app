<?php

namespace App\Http\Requests;

class PaymentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'payments' => ['required', 'array', 'min:1', 'max:5'],
            'payments.*.method' => ['required', 'in:cash,qris,transfer,card'],
            'payments.*.amount' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'payments.*.received' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
