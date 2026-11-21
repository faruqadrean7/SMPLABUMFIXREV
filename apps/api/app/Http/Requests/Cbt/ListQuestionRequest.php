<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class ListQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['nullable', 'uuid', 'exists:question_banks,id'],
            'subject_id' => ['nullable', 'uuid', 'exists:subjects,id'],
            'grade_level_id' => ['nullable', 'uuid', 'exists:grade_levels,id'],
            'question_type' => ['nullable', 'string', 'in:pg,pg_kompleks,bs,menjodohkan,isian,uraian'],
            'cognitive_level' => ['nullable', 'string', 'in:LOTS,MOTS,HOTS'],
            'difficulty' => ['nullable', 'integer', 'min:1', 'max:5'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
