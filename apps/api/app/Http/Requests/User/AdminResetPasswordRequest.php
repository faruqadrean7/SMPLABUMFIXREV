<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseApiRequest;

class AdminResetPasswordRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'new_password' => ['nullable', 'string', 'min:8', 'max:100'],
            'must_change_password' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.min' => 'Password baru minimal 8 karakter',
        ];
    }
}
