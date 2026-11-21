<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseApiRequest;

class RefreshTokenRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'refreshToken' => ['nullable', 'string'],
        ];
    }
}
