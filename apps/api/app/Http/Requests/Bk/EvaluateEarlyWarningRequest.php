<?php

namespace App\Http\Requests\Bk;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateEarlyWarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['uuid', 'exists:students,id'],
            'class_id' => ['nullable', 'uuid', 'exists:school_classes,id'],
            'attendance_threshold_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_absent_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'grade_drop_threshold' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'violation_points_threshold' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
