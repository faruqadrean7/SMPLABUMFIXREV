<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class PublishExamToGradebookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'component_id' => ['required', 'uuid', 'exists:grade_components,id'],
            'period_id' => ['required', 'uuid', 'exists:grade_periods,id'],
            'overwrite_locked' => ['sometimes', 'boolean'],
        ];
    }
}
