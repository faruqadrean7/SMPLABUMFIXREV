<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class StoreSemesterRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'string', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'academic_year_id.required' => 'ID Tahun Ajaran wajib disertakan',
            'academic_year_id.exists' => 'Tahun Ajaran tidak ditemukan di sistem',
            'name.required' => 'Nama semester wajib diisi (contoh: Ganjil atau Genap)',
            'start_date.required' => 'Tanggal mulai semester wajib diisi',
            'end_date.required' => 'Tanggal selesai semester wajib diisi',
            'end_date.after' => 'Tanggal selesai harus setelah tanggal mulai',
        ];
    }
}
