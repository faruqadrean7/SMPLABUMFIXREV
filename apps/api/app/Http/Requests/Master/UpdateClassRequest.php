<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;
use App\Models\SchoolClass;
use Illuminate\Validation\Rule;

class UpdateClassRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id');
        $class = SchoolClass::find($id);
        $academicYearId = $class?->academic_year_id;

        return [
            'grade_level_id' => ['nullable', 'string', 'exists:grade_levels,id'],
            'major_id' => ['nullable', 'string', 'exists:majors,id'],
            'homeroom_teacher_id' => ['nullable', 'string', 'exists:teachers,id'],
            'name' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('classes', 'name')->where(function ($query) use ($academicYearId) {
                    return $query->where('academic_year_id', $academicYearId)
                        ->whereNull('deleted_at');
                })->ignore($id),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'program_type' => ['nullable', 'in:reguler,tahfidz,icp,inklusi'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Nama rombel sudah ada pada tahun ajaran ini',
            'capacity.min' => 'Kapasitas kelas minimal 1 siswa',
            'capacity.max' => 'Kapasitas kelas maksimal 100 siswa',
        ];
    }
}
