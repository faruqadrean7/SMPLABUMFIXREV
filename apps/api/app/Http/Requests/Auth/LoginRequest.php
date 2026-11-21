<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseApiRequest;

class LoginRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'platform' => ['nullable', 'string', 'in:web,android,ios'],
            'fcm_token' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'Identitas (username, email, NIS, atau NIP) wajib diisi',
            'password.required' => 'Kata sandi wajib diisi',
            'platform.in' => 'Platform harus salah satu dari: web, android, ios',
        ];
    }
}
