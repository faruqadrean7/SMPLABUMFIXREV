<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class StoreAcademicYearRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:academic_years,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama tahun ajaran wajib diisi (contoh: 2026/2027)',
            'name.unique' => 'Nama tahun ajaran sudah terdaftar',
            'start_date.required' => 'Tanggal mulai wajib diisi',
            'end_date.required' => 'Tanggal selesai wajib diisi',
            'end_date.after' => 'Tanggal selesai harus setelah tanggal mulai',
        ];
    }
}
