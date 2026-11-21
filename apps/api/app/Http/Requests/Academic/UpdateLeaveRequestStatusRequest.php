<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class UpdateLeaveRequestStatusRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:disetujui,ditolak'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status keputusan izin wajib diisi',
            'status.in' => 'Status keputusan harus disetujui atau ditolak',
        ];
    }
}
