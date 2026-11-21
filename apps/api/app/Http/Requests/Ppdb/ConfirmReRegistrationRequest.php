<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class ConfirmReRegistrationRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.string' => 'Catatan daftar ulang harus berupa teks',
            'notes.max' => 'Catatan daftar ulang maksimal 1000 karakter',
        ];
    }
}
