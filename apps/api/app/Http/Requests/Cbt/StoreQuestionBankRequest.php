<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'grade_level_id' => ['nullable', 'uuid', 'exists:grade_levels,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama bank soal wajib diisi.',
            'name.max' => 'Nama bank soal maksimal 200 karakter.',
            'subject_id.required' => 'Mata pelajaran wajib dipilih.',
            'subject_id.uuid' => 'Format ID mata pelajaran tidak valid.',
            'subject_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'grade_level_id.uuid' => 'Format ID tingkat kelas tidak valid.',
            'grade_level_id.exists' => 'Tingkat kelas tidak ditemukan.',
        ];
    }
}
