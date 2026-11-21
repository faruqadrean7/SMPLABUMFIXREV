<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class GradeEssayAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answer_id' => ['required', 'uuid', 'exists:exam_answers,id'],
            'score' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'answer_id.required' => 'ID jawaban siswa wajib diisi.',
            'answer_id.uuid' => 'Format ID jawaban harus berupa UUID.',
            'answer_id.exists' => 'Jawaban siswa tidak ditemukan.',
            'score.required' => 'Skor nilai harus diisi.',
            'score.numeric' => 'Skor nilai harus berupa angka.',
            'score.min' => 'Skor nilai minimal adalah 0.',
        ];
    }
}
