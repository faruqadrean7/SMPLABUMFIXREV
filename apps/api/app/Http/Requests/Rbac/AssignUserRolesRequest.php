<?php

namespace App\Http\Requests\Rbac;

use App\Http\Requests\BaseApiRequest;

class AssignUserRolesRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*.role_id' => ['required', 'string', 'exists:roles,id'],
            'roles.*.scope_class_id' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'Daftar peran (roles) wajib diisi',
            'roles.min' => 'Pengguna minimal harus memiliki satu peran',
            'roles.*.role_id.required' => 'ID peran wajib disertakan',
            'roles.*.role_id.exists' => 'Peran yang dipilih tidak ditemukan di sistem',
        ];
    }
}
