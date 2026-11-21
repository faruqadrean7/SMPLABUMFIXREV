<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class VerifyApplicationRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:verified,revision'],
            'note' => ['required_if:status,revision', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status verifikasi pendaftaran wajib dipilih',
            'status.in' => 'Status verifikasi pendaftaran harus berupa verified atau revision',
            'note.required_if' => 'Catatan perbaikan / revisi wajib diisi jika pendaftaran memerlukan revisi',
            'note.max' => 'Catatan perbaikan maksimal 1000 karakter',
        ];
    }
}
