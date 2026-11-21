<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class VerifyDocumentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:valid,ditolak'],
            'reject_reason' => ['required_if:status,ditolak', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status verifikasi berkas wajib dipilih',
            'status.in' => 'Status verifikasi harus berupa valid atau ditolak',
            'reject_reason.required_if' => 'Alasan penolakan wajib diisi jika berkas ditolak',
            'reject_reason.max' => 'Alasan penolakan maksimal 500 karakter',
        ];
    }
}
