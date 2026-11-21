<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class ListTeachingMaterialRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'material_type' => ['nullable', 'string', 'in:modul,ATP,silabus,P5'],
            'grade_level_id' => ['nullable', 'uuid', 'exists:grade_levels,id'],
            'subject_id' => ['nullable', 'uuid', 'exists:subjects,id'],
            'uploaded_by' => ['nullable', 'uuid', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'material_type.in' => 'Jenis perangkat harus salah satu dari: modul, ATP, silabus, P5',
            'grade_level_id.exists' => 'Tingkat kelas tidak ditemukan di sistem',
            'subject_id.exists' => 'Mata pelajaran tidak ditemukan di sistem',
            'uploaded_by.exists' => 'Pengunggah tidak ditemukan di sistem',
        ];
    }
}
