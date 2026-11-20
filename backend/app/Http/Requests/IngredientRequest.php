<?php

namespace App\Http\Requests;

class IngredientRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'is_key' => ['boolean'],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
