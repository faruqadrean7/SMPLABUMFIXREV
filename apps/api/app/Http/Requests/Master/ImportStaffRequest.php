<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class ImportStaffRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Berkas import data staf wajib diunggah',
            'file.file' => 'Berkas yang diunggah tidak valid',
            'file.max' => 'Ukuran berkas maksimal adalah 10MB',
        ];
    }
}
