<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class BulkStoreGradeRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'component_id' => ['nullable', 'uuid', 'exists:grade_components,id'],
            'period_id' => ['required', 'uuid', 'exists:grade_periods,id'],
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.student_id' => ['required', 'uuid', 'exists:students,id'],
            'grades.*.component_id' => ['nullable', 'uuid', 'exists:grade_components,id'],
            'grades.*.value' => ['required', 'numeric', 'between:0,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'component_id.required' => 'Komponen nilai wajib dipilih',
            'component_id.exists' => 'Komponen nilai tidak ditemukan di sistem',
            'period_id.required' => 'Periode penilaian wajib dipilih',
            'period_id.exists' => 'Periode penilaian tidak ditemukan di sistem',
            'grades.required' => 'Daftar nilai wajib diisi',
            'grades.min' => 'Daftar nilai minimal berisi 1 data siswa',
            'grades.*.student_id.required' => 'ID siswa wajib diisi',
            'grades.*.student_id.exists' => 'Siswa tidak ditemukan di sistem',
            'grades.*.value.required' => 'Nilai siswa wajib diisi',
            'grades.*.value.between' => 'Nilai siswa harus berada di antara 0 sampai 100',
        ];
    }
}
