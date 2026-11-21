<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class DecideLeaveRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:disetujui,ditolak'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status keputusan izin wajib diisi',
            'status.in' => 'Status keputusan harus salah satu dari: disetujui, ditolak',
            'decision_notes.max' => 'Catatan keputusan maksimal 1000 karakter',
        ];
    }
}
