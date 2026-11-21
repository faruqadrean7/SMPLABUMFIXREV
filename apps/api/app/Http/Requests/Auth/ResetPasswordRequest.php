<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseApiRequest;

class ResetPasswordRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Token pemulihan kata sandi wajib disertakan',
            'password.required' => 'Kata sandi baru wajib diisi',
            'password.min' => 'Kata sandi minimal harus berjumlah 8 karakter',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok',
        ];
    }
}
