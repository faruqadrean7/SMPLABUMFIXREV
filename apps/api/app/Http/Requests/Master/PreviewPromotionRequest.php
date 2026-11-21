<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class PreviewPromotionRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'source_academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'source_class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'target_academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'default_target_class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'overrides' => ['nullable', 'array'],
            'overrides.*.student_id' => ['required_with:overrides', 'uuid', 'exists:students,id'],
            'overrides.*.action' => ['required_with:overrides', 'string', 'in:promote,retain,graduate,drop_out'],
            'overrides.*.target_class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'overrides.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'source_academic_year_id.required' => 'Tahun ajaran asal wajib dipilih',
            'source_academic_year_id.exists' => 'Tahun ajaran asal tidak ditemukan',
            'source_class_id.exists' => 'Kelas asal tidak ditemukan',
            'target_academic_year_id.exists' => 'Tahun ajaran tujuan tidak ditemukan',
            'default_target_class_id.exists' => 'Kelas tujuan default tidak ditemukan',
            'overrides.*.action.in' => 'Aksi kenaikan harus promote, retain, graduate, atau drop_out',
        ];
    }
}
