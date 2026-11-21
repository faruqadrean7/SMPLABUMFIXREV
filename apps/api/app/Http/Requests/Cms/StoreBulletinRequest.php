<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;

class StoreBulletinRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'file_id' => ['required', 'uuid', 'exists:files,id'],
            'cover_file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul buletin wajib diisi',
            'file_id.required' => 'File dokumen buletin wajib diisi',
            'file_id.exists' => 'File dokumen buletin tidak ditemukan',
            'cover_file_id.exists' => 'File sampul tidak valid',
        ];
    }
}
