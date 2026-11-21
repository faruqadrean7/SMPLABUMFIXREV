<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class AssignExamQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_id' => ['required', 'uuid', 'exists:questions,id'],
            'questions.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'questions.*.weight_override' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'questions.*.package_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'questions.required' => 'Daftar butir soal wajib disertakan.',
            'questions.min' => 'Minimal 1 butir soal untuk dimasukkan ke dalam ujian.',
            'questions.*.question_id.required' => 'ID butir soal wajib diisi.',
            'questions.*.question_id.exists' => 'Butir soal tidak ditemukan di bank soal.',
        ];
    }
}
