<?php

namespace App\Http\Requests\Rbac;

use App\Http\Requests\BaseApiRequest;

class UpdateRoleRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['string', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Nama role maksimal 100 karakter',
            'permission_ids.*.exists' => 'Salah satu ID izin yang dipilih tidak valid',
        ];
    }
}
