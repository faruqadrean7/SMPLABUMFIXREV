<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class BatchTeachingAssignmentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'string', 'exists:semesters,id'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.class_id' => ['required', 'string', 'exists:classes,id'],
            'assignments.*.subject_id' => ['required', 'string', 'exists:subjects,id'],
            'assignments.*.teacher_id' => ['required', 'string', 'exists:teachers,id'],
            'assignments.*.hours_per_week' => ['nullable', 'integer', 'min:1', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'semester_id.required' => 'Semester wajib dipilih',
            'assignments.required' => 'Daftar penugasan mengajar wajib diisi',
            'assignments.min' => 'Minimal sertakan 1 penugasan mengajar',
        ];
    }
}
