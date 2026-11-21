<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class ExecutePromotionRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'source_academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'target_academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'effective_date' => ['required', 'date'],
            'promotions' => ['required', 'array', 'min:1'],
            'promotions.*.student_id' => ['required', 'uuid', 'exists:students,id'],
            'promotions.*.current_class_id' => ['required', 'uuid', 'exists:classes,id'],
            'promotions.*.action' => ['required', 'string', 'in:promote,retain,graduate,drop_out'],
            'promotions.*.target_class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'promotions.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'source_academic_year_id.required' => 'Tahun ajaran asal wajib dipilih',
            'source_academic_year_id.exists' => 'Tahun ajaran asal tidak ditemukan',
            'target_academic_year_id.exists' => 'Tahun ajaran tujuan tidak ditemukan',
            'effective_date.required' => 'Tanggal efektif pelaksanaan wajib diisi',
            'effective_date.date' => 'Format tanggal efektif tidak valid',
            'promotions.required' => 'Daftar keputusan kenaikan/kelulusan siswa wajib disertakan',
            'promotions.min' => 'Minimal 1 siswa harus diproses dalam eksekusi',
            'promotions.*.student_id.required' => 'ID siswa wajib diisi',
            'promotions.*.current_class_id.required' => 'ID kelas asal wajib diisi',
            'promotions.*.action.required' => 'Aksi kenaikan/kelulusan wajib diisi',
            'promotions.*.action.in' => 'Aksi harus promote, retain, graduate, atau drop_out',
        ];
    }
}
