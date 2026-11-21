<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class UploadApplicantDocumentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'Jenis dokumen wajib diisi',
            'document_type.max' => 'Jenis dokumen maksimal 100 karakter',
            'file.required' => 'Berkas dokumen wajib diunggah',
            'file.file' => 'Berkas yang diunggah harus berupa file yang valid',
            'file.mimes' => 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG',
            'file.max' => 'Ukuran berkas maksimal adalah 2 MB (2048 KB)',
        ];
    }
}
