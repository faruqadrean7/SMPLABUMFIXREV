<?php

namespace App\Http\Requests\Guest;

use App\Http\Requests\BaseApiRequest;

class RegisterGuestRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'uuid'],
            'full_name' => ['required', 'string', 'min:2', 'max:200'],
            'institution' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', 'min:8', 'max:30'],
            'purpose' => ['required', 'string', 'min:3', 'max:1000'],
            'target_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'photo_file_id' => ['nullable', 'uuid', 'exists:files,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap tamu wajib diisi',
            'full_name.min' => 'Nama lengkap minimal 2 karakter',
            'phone.required' => 'Nomor telepon/WhatsApp wajib diisi',
            'phone.min' => 'Nomor telepon minimal 8 digit',
            'purpose.required' => 'Keperluan kunjungan wajib diisi',
            'purpose.min' => 'Keperluan kunjungan minimal 3 karakter',
            'target_user_id.exists' => 'Pejabat/guru tujuan tidak ditemukan di sistem',
            'photo_file_id.exists' => 'Berkas foto tidak ditemukan di sistem',
        ];
    }
}
