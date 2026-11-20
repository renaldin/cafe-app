<?php

namespace App\Http\Requests;

class ModifierGroupRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'selection' => ['required', 'in:single,multiple'],
            'is_required' => ['boolean'],
            'min_select' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_select' => ['nullable', 'integer', 'min:1', 'max:20'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'options' => ['required', 'array', 'min:1', 'max:30'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.name' => ['required', 'string', 'max:100'],
            'options.*.price_delta' => ['required', 'integer', 'min:-10000000', 'max:10000000'],
            'options.*.is_available' => ['boolean'],
            'options.*.is_default' => ['boolean'],
            'options.*.recipes' => ['nullable', 'array'],
            'options.*.recipes.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'options.*.recipes.*.qty' => ['required', 'numeric', 'min:-100000', 'max:100000'],
        ];
    }
}
