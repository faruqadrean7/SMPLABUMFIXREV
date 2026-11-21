<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class ResendOtpRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'exists:users,email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Alamat email wajib diisi',
            'email.email' => 'Format alamat email tidak valid',
            'email.exists' => 'Alamat email tidak ditemukan dalam sistem',
        ];
    }
}
