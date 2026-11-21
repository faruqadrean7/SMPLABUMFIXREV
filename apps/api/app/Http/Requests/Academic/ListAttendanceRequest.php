<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class ListAttendanceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'journal_id' => ['nullable', 'uuid', 'exists:journals,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'in:hadir,izin,sakit,alpa,terlambat'],
            'source' => ['nullable', 'string', 'in:kelas,gerbang'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
