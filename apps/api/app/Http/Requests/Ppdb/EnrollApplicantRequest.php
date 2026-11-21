<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class EnrollApplicantRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'nis' => ['nullable', 'string', 'max:30', 'unique:students,nis'],
            'nisn' => ['nullable', 'string', 'max:30', 'unique:students,nisn'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'entry_date' => ['nullable', 'date'],
            'force_enroll' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nis.unique' => 'NIS sudah digunakan oleh siswa lain',
            'nis.max' => 'NIS maksimal 30 karakter',
            'nisn.unique' => 'NISN sudah digunakan oleh siswa lain',
            'nisn.max' => 'NISN maksimal 30 karakter',
            'class_id.uuid' => 'ID kelas tidak valid',
            'class_id.exists' => 'Kelas yang dipilih tidak ditemukan',
            'academic_year_id.uuid' => 'ID tahun ajaran tidak valid',
            'academic_year_id.exists' => 'Tahun ajaran tidak ditemukan',
            'entry_date.date' => 'Tanggal masuk harus berformat tanggal yang valid',
            'notes.max' => 'Catatan konversi maksimal 1000 karakter',
        ];
    }
}
