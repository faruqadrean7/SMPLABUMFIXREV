<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class ListReportCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'uuid', 'exists:semesters,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'status' => ['nullable', 'string', 'in:draft,terkunci,disetujui,terbit'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
