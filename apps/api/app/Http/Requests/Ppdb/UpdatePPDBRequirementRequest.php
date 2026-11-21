<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class UpdatePPDBRequirementRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'document_type' => ['sometimes', 'required', 'string', 'max:100'],
            'is_required' => ['sometimes', 'required', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'Jenis dokumen persyaratan wajib diisi',
            'is_required.required' => 'Status wajib dokumen harus ditentukan',
        ];
    }
}
