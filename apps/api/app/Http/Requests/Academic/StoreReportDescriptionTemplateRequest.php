<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportDescriptionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'level' => ['required', 'string', 'in:tuntas,belum'],
            'template_text' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject_id.required' => 'Mata pelajaran wajib dipilih.',
            'subject_id.uuid' => 'Format ID mata pelajaran tidak valid.',
            'subject_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'level.required' => 'Level capaian wajib diisi (tuntas / belum).',
            'level.in' => 'Level capaian hanya boleh tuntas atau belum.',
            'template_text.required' => 'Teks template deskripsi capaian wajib diisi.',
            'template_text.min' => 'Teks template minimal 5 karakter.',
            'template_text.max' => 'Teks template maksimal 1000 karakter.',
        ];
    }
}
