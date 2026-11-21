<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class SaveExamAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_id' => ['required', 'uuid', 'exists:questions,id'],
            'answer' => ['nullable'],
            'is_flagged' => ['nullable', 'boolean'],
            'answered_at' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'client_timestamp' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'question_id.required' => 'ID butir soal wajib diisi.',
            'question_id.uuid' => 'Format ID butir soal harus UUID valid.',
            'question_id.exists' => 'Butir soal tidak ditemukan dalam sistem.',
            'is_flagged.boolean' => 'Flag ragu-ragu harus berupa boolean.',
        ];
    }
}
