<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'username' => ['sometimes', 'required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'suspended'])],
            'must_change_password' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Username sudah digunakan oleh akun lain',
            'username.min' => 'Username minimal 3 karakter',
            'username.regex' => 'Username hanya boleh memuat huruf, angka, titik, strip, atau garis bawah',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah terdaftar pada akun lain',
            'status.in' => 'Status pengguna harus bernilai active, inactive, atau suspended',
        ];
    }
}
