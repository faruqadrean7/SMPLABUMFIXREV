<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class ListLeaveRequestRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'status' => ['nullable', 'string', 'in:menunggu,disetujui,ditolak'],
            'leave_type' => ['nullable', 'string', 'in:sakit,izin,dispen'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
