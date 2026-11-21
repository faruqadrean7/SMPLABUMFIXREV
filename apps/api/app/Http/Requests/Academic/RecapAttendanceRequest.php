<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class RecapAttendanceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'journal_id' => ['nullable', 'uuid', 'exists:journals,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
