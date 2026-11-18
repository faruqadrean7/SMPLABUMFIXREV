<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class StoreTeacherRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'nip' => ['nullable', 'string', 'max:50', 'unique:teachers,nip'],
            'nuptk' => ['nullable', 'string', 'max:50', 'unique:teachers,nuptk'],
            'full_name' => ['required', 'string', 'max:200'],
            'position' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:tetap,honorer,kontrak'],
            'photo_file_id' => ['nullable', 'string', 'exists:files,id'],
            'user_id' => ['nullable', 'string', 'exists:users,id', 'unique:teachers,user_id'],
            'create_user_account' => ['nullable', 'boolean'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:100', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'nip.unique' => 'NIP sudah terdaftar di sistem',
            'nuptk.unique' => 'NUPTK sudah terdaftar di sistem',
            'full_name.required' => 'Nama lengkap guru wajib diisi',
            'status.in' => 'Status kepegawaian harus salah satu dari: tetap, honorer, kontrak',
            'user_id.unique' => 'Akun user sudah terhubung dengan guru lain',
        ];
    }
}
