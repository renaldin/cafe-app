<?php

namespace App\Http\Requests;

class RefundRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'restock' => ['boolean'],
            'method' => ['nullable', 'in:cash,qris,transfer,card'],
        ];
    }
}
