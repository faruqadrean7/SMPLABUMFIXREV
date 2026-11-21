<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class StoreTeachingAssignmentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'class_id' => ['required', 'uuid', 'exists:classes,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'hours_per_week' => ['nullable', 'integer', 'min:1', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'semester_id.required' => 'Semester wajib dipilih',
            'semester_id.exists' => 'Semester tidak ditemukan di sistem',
            'class_id.required' => 'Kelas wajib dipilih',
            'class_id.exists' => 'Kelas tidak ditemukan di sistem',
            'subject_id.required' => 'Mata pelajaran wajib dipilih',
            'subject_id.exists' => 'Mata pelajaran tidak ditemukan di sistem',
            'teacher_id.required' => 'Guru pengampu wajib dipilih',
            'teacher_id.exists' => 'Guru pengampu tidak ditemukan di sistem',
            'hours_per_week.min' => 'Beban jam per minggu minimal 1 jam',
            'hours_per_week.max' => 'Beban jam per minggu maksimal 40 jam',
        ];
    }
}
