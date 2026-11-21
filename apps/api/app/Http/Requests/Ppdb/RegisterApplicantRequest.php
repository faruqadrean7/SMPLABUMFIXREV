<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class RegisterApplicantRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8'],
            'nik' => ['nullable', 'string', 'size:16', 'regex:/^[0-9]{16}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap wajib diisi',
            'full_name.max' => 'Nama lengkap maksimal 200 karakter',
            'email.required' => 'Alamat email wajib diisi',
            'email.email' => 'Format alamat email tidak valid',
            'email.unique' => 'Alamat email ini sudah terdaftar pada sistem',
            'phone.required' => 'Nomor WhatsApp / telepon wajib diisi',
            'password.required' => 'Kata sandi wajib diisi',
            'password.min' => 'Kata sandi minimal 8 karakter',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok',
            'password_confirmation.required' => 'Konfirmasi kata sandi wajib diisi',
            'nik.size' => 'NIK harus berjumlah 16 digit angka',
            'nik.regex' => 'NIK harus berupa 16 digit angka',
        ];
    }
}
