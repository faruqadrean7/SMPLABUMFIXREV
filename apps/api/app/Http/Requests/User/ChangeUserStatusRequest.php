<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class ChangeUserStatusRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'suspended'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status baru wajib ditentukan',
            'status.in' => 'Status pengguna harus berupa active, inactive, atau suspended',
        ];
    }
}
