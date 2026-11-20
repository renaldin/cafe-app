<?php

namespace App\Http\Requests;

class RoleRequest extends ApiRequest
{
    public function rules(): array
    {
        $id = $this->route('role')?->id;

        return [
            'name' => ['required', 'string', 'max:50', 'unique:roles,name,'.($id ?? 'NULL')],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
