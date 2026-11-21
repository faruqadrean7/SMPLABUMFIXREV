<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseApiRequest;

class ForgotPasswordRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'identifier' => ['required_without:email', 'string'],
            'email' => ['required_without:identifier', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required_without' => 'Email atau username akun wajib diisi',
            'email.required_without' => 'Email atau username akun wajib diisi',
        ];
    }
}
