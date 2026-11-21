<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class StorePPDBRequirementRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'track_id' => ['required', 'uuid', 'exists:ppdb_tracks,id'],
            'document_type' => ['required', 'string', 'max:100'],
            'is_required' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'track_id.required' => 'Jalur pendaftaran wajib dipilih',
            'track_id.exists' => 'Jalur pendaftaran tidak ditemukan',
            'document_type.required' => 'Jenis dokumen persyaratan wajib diisi',
            'is_required.required' => 'Status wajib dokumen harus ditentukan',
        ];
    }
}
