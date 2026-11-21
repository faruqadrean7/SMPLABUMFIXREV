<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class ListAchievementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('only_my')) {
            $this->merge([
                'only_my' => filter_var($this->input('only_my'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'uuid'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'competition_id' => ['nullable', 'uuid', 'exists:competitions,id'],
            'rank_level' => ['nullable', 'string', 'max:100'],
            'verification_status' => ['nullable', 'string', 'in:pending,verified,rejected'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'search' => ['nullable', 'string', 'max:100'],
            'only_my' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
