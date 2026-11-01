<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class StoreGuardianRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string', 'exists:users,id', 'unique:guardians,user_id'],
            'create_user_account' => ['nullable', 'boolean'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:100', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap orang tua/wali wajib diisi',
            'phone.required' => 'Nomor telepon/HP wajib diisi',
            'user_id.unique' => 'Akun user sudah terhubung dengan data orang tua/wali lain',
            'username.unique' => 'Username ini sudah digunakan akun lain',
            'email.unique' => 'Email ini sudah digunakan akun lain',
            'password.min' => 'Kata sandi minimal 6 karakter',
        ];
    }
}
