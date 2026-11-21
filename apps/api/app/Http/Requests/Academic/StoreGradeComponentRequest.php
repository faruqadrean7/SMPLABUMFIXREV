<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreGradeComponentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('grade_components')->where(function ($query) {
                    return $query->where('subject_id', $this->subject_id)
                        ->where('semester_id', $this->semester_id)
                        ->where('class_id', $this->class_id);
                }),
            ],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'weight' => ['required', 'numeric', 'between:0.01,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject_id.required' => 'Mata pelajaran wajib dipilih',
            'subject_id.exists' => 'Mata pelajaran tidak ditemukan di sistem',
            'semester_id.required' => 'Semester wajib dipilih',
            'semester_id.exists' => 'Semester tidak ditemukan di sistem',
            'class_id.exists' => 'Kelas tidak ditemukan di sistem',
            'code.required' => 'Kode komponen nilai wajib diisi',
            'code.unique' => 'Kode komponen nilai sudah digunakan untuk mapel, semester, dan kelas ini',
            'name.required' => 'Nama komponen nilai wajib diisi',
            'weight.required' => 'Bobot komponen nilai wajib diisi',
            'weight.between' => 'Bobot komponen nilai harus berada di antara 0.01 sampai 100',
        ];
    }
}
