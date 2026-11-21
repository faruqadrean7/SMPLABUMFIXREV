<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class VerifyOtpRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Alamat email wajib diisi',
            'email.email' => 'Format alamat email tidak valid',
            'otp.required' => 'Kode OTP wajib diisi',
            'otp.size' => 'Kode OTP harus berupa 6 digit angka',
            'otp.regex' => 'Kode OTP harus berupa 6 digit angka numerik',
        ];
    }
}
