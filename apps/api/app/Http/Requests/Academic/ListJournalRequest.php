<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class ListJournalRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'schedule_id' => ['nullable', 'uuid', 'exists:schedules,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'subject_id' => ['nullable', 'uuid', 'exists:subjects,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
