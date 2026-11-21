<?php

namespace App\Http\Requests\Guest;

use App\Http\Requests\BaseApiRequest;

class UploadGuestSignatureRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'signature_data' => ['required_without:signature_file', 'nullable', 'string'],
            'signature_file' => ['required_without:signature_data', 'nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'signature_data.required_without' => 'Tanda tangan digital wajib diisi (berupa data canvas base64 atau unggahan berkas gambar).',
            'signature_file.required_without' => 'Tanda tangan digital wajib diisi (berupa data canvas base64 atau unggahan berkas gambar).',
            'signature_file.image' => 'Berkas tanda tangan harus berupa gambar yang valid.',
            'signature_file.mimes' => 'Format gambar tanda tangan harus PNG, JPG, JPEG, atau WEBP.',
            'signature_file.max' => 'Ukuran berkas tanda tangan maksimal 2 MB.',
        ];
    }
}
