<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;

class UpdateAlbumRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'cover_file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul album wajib diisi',
            'cover_file_id.exists' => 'File sampul tidak valid',
        ];
    }
}
