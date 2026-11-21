<?php

namespace App\Http\Requests\Rbac;

use App\Http\Requests\BaseApiRequest;

class StoreRoleRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:roles,code'],
            'name' => ['required', 'string', 'max:100'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['string', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode role wajib diisi',
            'code.unique' => 'Kode role sudah digunakan',
            'code.regex' => 'Kode role hanya boleh memuat huruf kecil, angka, dan garis bawah (_)',
            'name.required' => 'Nama role wajib diisi',
            'permission_ids.*.exists' => 'Salah satu ID izin yang dipilih tidak valid',
        ];
    }
}
