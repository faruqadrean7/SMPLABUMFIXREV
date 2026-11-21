<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseApiRequest;

class RegisterDeviceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'fcm_token' => ['required_without:token', 'nullable', 'string', 'max:1000'],
            'token' => ['required_without:fcm_token', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'platform.required' => 'Platform perangkat wajib dipilih',
            'platform.in' => 'Platform harus salah satu dari: android, ios, web',
            'fcm_token.required_without' => 'Token perangkat wajib diisi',
            'token.required_without' => 'Token perangkat wajib diisi',
            'fcm_token.max' => 'Token perangkat maksimal 1000 karakter',
            'token.max' => 'Token perangkat maksimal 1000 karakter',
        ];
    }
}
