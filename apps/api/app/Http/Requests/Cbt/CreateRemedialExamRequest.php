<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class CreateRemedialExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:200'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'duration_min' => ['nullable', 'integer', 'min:5', 'max:300'],
            'passing_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_remedial_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'question_bank_id' => ['nullable', 'uuid'],
            'question_ids' => ['nullable', 'array'],
            'question_ids.*' => ['uuid'],
            'target_student_ids' => ['nullable', 'array'],
            'target_student_ids.*' => ['uuid'],
        ];
    }
}
