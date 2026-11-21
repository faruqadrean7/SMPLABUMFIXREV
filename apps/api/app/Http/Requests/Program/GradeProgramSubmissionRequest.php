<?php

namespace App\Http\Requests\Program;

use Illuminate\Foundation\Http\FormRequest;

class GradeProgramSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:dinilai,perlu_revisi'],
        ];
    }

    public function messages(): array
    {
        return [
            'score.required' => 'Nilai tugas wajib diisi.',
            'score.numeric' => 'Nilai tugas harus berupa angka.',
            'score.min' => 'Nilai minimal adalah 0.',
            'score.max' => 'Nilai maksimal adalah 100.',
            'status.in' => 'Status penilaian harus salah satu dari: dinilai, perlu_revisi.',
        ];
    }
}
