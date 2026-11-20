<?php

namespace App\Http\Requests;

class TableRequest extends ApiRequest
{
    public function rules(): array
    {
        $id = $this->route('table')?->id;

        return [
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:tables,code,'.($id ?? 'NULL')],
            'name' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'pos_x' => ['nullable', 'integer', 'min:0', 'max:50'],
            'pos_y' => ['nullable', 'integer', 'min:0', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }
}
