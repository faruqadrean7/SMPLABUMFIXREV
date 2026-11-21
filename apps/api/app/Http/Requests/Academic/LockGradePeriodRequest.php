<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class LockGradePeriodRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:open,locked'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status kunci periode wajib diisi',
            'status.in' => 'Status harus open atau locked',
        ];
    }
}
