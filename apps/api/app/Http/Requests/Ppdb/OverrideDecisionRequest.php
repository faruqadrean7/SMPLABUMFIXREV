<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class OverrideDecisionRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:diterima,cadangan,ditolak'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan seleksi wajib ditentukan',
            'decision.in' => 'Keputusan seleksi harus salah satu dari: diterima, cadangan, atau ditolak',
            'reason.required' => 'Alasan penetapan / pembatalan keputusan wajib diisi',
            'reason.max' => 'Alasan keputusan maksimal 500 karakter',
        ];
    }
}
