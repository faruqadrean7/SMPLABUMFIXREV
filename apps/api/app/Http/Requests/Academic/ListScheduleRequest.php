<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class ListScheduleRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('all')) {
            $this->merge([
                'all' => filter_var($this->input('all'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->input('all'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'uuid', 'exists:semesters,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
            'subject_id' => ['nullable', 'uuid', 'exists:subjects,id'],
            'day_of_week' => ['nullable', 'integer', 'between:1,7'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'all' => ['nullable', 'boolean'],
        ];
    }
}
