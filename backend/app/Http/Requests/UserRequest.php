<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class UserRequest extends ApiRequest
{
    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username,'.($id ?? 'NULL')],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email,'.($id ?? 'NULL')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$id ? 'nullable' : 'required', 'string', Password::min(8)],
            'is_active' => ['boolean'],
            'role' => ['required', 'string', 'exists:roles,name'],
        ];
    }
}
