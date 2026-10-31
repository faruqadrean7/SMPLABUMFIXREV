<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreClassRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'string', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'string', 'exists:grade_levels,id'],
            'major_id' => ['nullable', 'string', 'exists:majors,id'],
            'homeroom_teacher_id' => ['nullable', 'string', 'exists:teachers,id'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('classes', 'name')->where(function ($query) {
                    return $query->where('academic_year_id', $this->input('academic_year_id'))
                        ->whereNull('deleted_at');
                }),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'program_type' => ['nullable', 'in:reguler,tahfidz,icp,inklusi'],
        ];
    }

    public function messages(): array
    {
        return [
            'academic_year_id.required' => 'Tahun ajaran wajib dipilih',
            'academic_year_id.exists' => 'Tahun ajaran tidak ditemukan di sistem',
            'grade_level_id.required' => 'Tingkat kelas wajib dipilih',
            'grade_level_id.exists' => 'Tingkat kelas tidak ditemukan di sistem',
            'name.required' => 'Nama rombel/kelas wajib diisi',
            'name.unique' => 'Nama rombel sudah ada pada tahun ajaran ini',
            'capacity.min' => 'Kapasitas kelas minimal 1 siswa',
            'capacity.max' => 'Kapasitas kelas maksimal 100 siswa',
        ];
    }
}
