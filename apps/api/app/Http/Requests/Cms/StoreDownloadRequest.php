<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;
use App\Models\Download;
use Illuminate\Validation\Rule;

class StoreDownloadRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'max:100'],
            'visibility' => ['required', 'string', Rule::in(Download::VISIBILITIES)],
            'file_id' => ['required', 'uuid', 'exists:files,id'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul dokumen wajib diisi',
            'category.required' => 'Kategori dokumen wajib diisi',
            'visibility.required' => 'Visibilitas dokumen wajib diisi',
            'visibility.in' => 'Visibilitas dokumen harus berupa publik atau internal',
            'file_id.required' => 'File dokumen wajib dipilih',
            'file_id.exists' => 'File dokumen tidak ditemukan',
        ];
    }
}
