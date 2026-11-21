<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class ChangeStudentStatusRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:aktif,lulus,pindah,keluar'],
            'reason' => ['nullable', 'string', 'max:500'],
            'effective_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status siswa wajib ditentukan',
            'status.in' => 'Status siswa harus salah satu dari: aktif, lulus, pindah, keluar',
            'effective_date.required' => 'Tanggal efektif perubahan status wajib diisi',
        ];
    }
}
