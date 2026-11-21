<?php

namespace App\Http\Requests\Hum;

use Illuminate\Foundation\Http\FormRequest;

class StoreTracerSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'target_graduation_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['required', 'string'],
            'questions.*.question' => ['required', 'string'],
            'questions.*.type' => ['required', 'string', 'in:text,choice,multiple_choice,rating'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
