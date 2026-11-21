<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class UpdateSemesterRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ];
    }
}
