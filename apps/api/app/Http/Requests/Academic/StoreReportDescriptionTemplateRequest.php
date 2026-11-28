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
            'level' => ['required', 'string', 'in:tuntas,belum,sangat_baik,baik,cukup,perlu_bimbingan'],
            'kktp_threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'template_text' => ['required', 'string', 'min:5', 'max:1500'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject_id.required' => 'Mata pelajaran wajib dipilih.',
            'subject_id.uuid' => 'Format ID mata pelajaran tidak valid.',
            'subject_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'level.required' => 'Level capaian wajib diisi.',
            'level.in' => 'Level capaian harus salah satu dari sangat_baik, baik, cukup, perlu_bimbingan, tuntas, atau belum.',
            'template_text.required' => 'Teks template deskripsi capaian wajib diisi.',
            'template_text.min' => 'Teks template minimal 5 karakter.',
            'template_text.max' => 'Teks template maksimal 1500 karakter.',
        ];
    }
}
