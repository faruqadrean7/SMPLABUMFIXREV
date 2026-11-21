<?php

namespace App\Http\Requests\Storage;

use App\Http\Requests\BaseApiRequest;

class ConfirmFileRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'checksum' => ['nullable', 'string', 'max:100'],
            'owner_type' => ['nullable', 'string', 'max:50'],
            'owner_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Token sesi presign wajib diisi.',
            'owner_id.uuid' => 'Format owner_id harus berupa UUID yang valid.',
        ];
    }
}
