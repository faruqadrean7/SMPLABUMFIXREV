<?php

namespace App\Http\Requests\Complaint;

use App\Http\Requests\BaseApiRequest;

class PresignComplaintAttachmentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'in:image/jpeg,image/png,image/webp'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:5242880'],
        ];
    }

    public function messages(): array
    {
        return [
            'filename.required' => 'Nama berkas lampiran wajib diisi',
            'mime_type.required' => 'Tipe MIME berkas wajib diisi',
            'mime_type.in' => 'Format berkas tidak diizinkan. Hanya gambar JPG, PNG, atau WebP',
            'size_bytes.required' => 'Ukuran berkas wajib diisi',
            'size_bytes.max' => 'Ukuran berkas melebihi batas maksimum 5 MB',
        ];
    }
}
