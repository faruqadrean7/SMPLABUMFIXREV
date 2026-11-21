<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class ImportQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'uuid', 'exists:question_banks,id'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_type' => ['required', 'string', 'in:pg,pg_kompleks,bs,menjodohkan,isian,uraian'],
            'questions.*.body' => ['required', 'array'],
            'questions.*.body.content' => ['required', 'string'],
            'questions.*.cp_code' => ['nullable', 'string', 'max:100'],
            'questions.*.cognitive_level' => ['nullable', 'string', 'in:LOTS,MOTS,HOTS'],
            'questions.*.difficulty' => ['nullable', 'integer', 'min:1', 'max:5'],
            'questions.*.weight' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'questions.*.explanation' => ['nullable', 'string'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*.label' => ['required_with:questions.*.options', 'string'],
            'questions.*.options.*.body' => ['required_with:questions.*.options', 'array'],
            'questions.*.options.*.is_correct' => ['nullable', 'boolean'],
            'questions.*.options.*.match_key' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_id.required' => 'ID bank soal target wajib disertakan.',
            'questions.required' => 'Daftar butir soal (questions) wajib diisi.',
            'questions.min' => 'Minimal 1 butir soal untuk diimport.',
        ];
    }
}
