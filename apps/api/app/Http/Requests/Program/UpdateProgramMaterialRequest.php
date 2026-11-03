<?php

namespace App\Http\Requests\Program;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'material_type' => ['sometimes', 'required', 'string', 'in:murottal,tajwid,panduan,reading,listening,past_paper,modul_adaptif,video,audio,dokumen,lainnya'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'target_scope' => ['nullable', 'string', 'in:all,class,student'],
            'assigned_student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'meta' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul materi wajib diisi.',
            'material_type.in' => 'Tipe materi tidak valid.',
            'external_url.url' => 'Format tautan eksternal tidak valid.',
        ];
    }
}
