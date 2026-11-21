<?php

namespace App\Http\Requests\Ntf;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'fcm_token' => ['required_without:token', 'nullable', 'string', 'max:1000'],
            'token' => ['required_without:fcm_token', 'nullable', 'string', 'max:1000'],
        ];
    }
}
