<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'suspended'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string'],
            'must_change_password' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Username akun pengguna wajib diisi',
            'username.unique' => 'Username sudah digunakan oleh akun lain',
            'username.min' => 'Username minimal 3 karakter',
            'username.regex' => 'Username hanya boleh memuat huruf, angka, titik, strip, atau garis bawah',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah terdaftar pada akun lain',
            'password.min' => 'Password minimal 8 karakter',
            'status.in' => 'Status pengguna harus bernilai active, inactive, atau suspended',
        ];
    }
}
