<?php

namespace App\Http\Requests\Hum;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTracerSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'uuid'],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'target_graduation_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'questions' => ['sometimes', 'required', 'array', 'min:1'],
            'questions.*.id' => ['required_with:questions', 'string'],
            'questions.*.question' => ['required_with:questions', 'string'],
            'questions.*.type' => ['required_with:questions', 'string', 'in:text,choice,multiple_choice,rating'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
