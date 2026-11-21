<?php

namespace App\Http\Requests\Complaint;

use App\Http\Requests\BaseApiRequest;

class ConfirmComplaintAttachmentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'token' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Format berkas tidak diizinkan. Hanya gambar JPG, PNG, atau WebP',
            'file.max' => 'Ukuran berkas melebihi batas maksimum 5 MB',
        ];
    }
}
