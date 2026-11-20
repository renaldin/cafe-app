<?php

namespace App\Http\Requests;

class MenuRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['is_available', 'is_active', 'is_bundle'] as $b) {
            if ($this->has($b)) {
                $this->merge([$b => filter_var($this->input($b), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    public function rules(): array
    {
        $id = $this->route('menu')?->id;

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'station_id' => ['nullable', 'integer', 'exists:stations,id'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:menus,sku,'.($id ?? 'NULL')],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['string', 'in:best_seller,new,spicy,vegan,recommended'],
            'is_available' => ['boolean'],
            'is_active' => ['boolean'],
            'is_bundle' => ['boolean'],
            'available_from' => ['nullable', 'date_format:H:i', 'required_with:available_until'],
            'available_until' => ['nullable', 'date_format:H:i', 'required_with:available_from'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'modifier_group_ids' => ['nullable', 'array'],
            'modifier_group_ids.*' => ['integer', 'exists:modifier_groups,id'],
            'bundle_items' => ['nullable', 'array'],
            'bundle_items.*.menu_id' => ['required', 'integer', 'exists:menus,id'],
            'bundle_items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }
}
