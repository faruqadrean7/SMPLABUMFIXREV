<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class UpdateStudentGuardianRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'relationship' => ['nullable', 'string', 'max:50'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
