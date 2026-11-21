<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseApiRequest;

class ChangePasswordRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Kata sandi saat ini wajib diisi',
            'new_password.required' => 'Kata sandi baru wajib diisi',
            'new_password.min' => 'Kata sandi baru minimal harus berjumlah 8 karakter',
            'new_password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok',
        ];
    }
}
