<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class StoreExtracurricularJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'extracurricular_id' => ['required', 'uuid', 'exists:extracurriculars,id'],
            'session_date' => ['required', 'date'],
            'start_time' => ['nullable', 'string', 'max:10'],
            'end_time' => ['nullable', 'string', 'max:10'],
            'activity' => ['required', 'string', 'max:2000'],
            'attendance' => ['nullable', 'array'],
            'attendance.*.student_id' => ['required_with:attendance', 'uuid', 'exists:students,id'],
            'attendance.*.status' => ['required_with:attendance', 'string', 'in:hadir,izin,sakit,alpa'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
