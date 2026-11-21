<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class StoreCharacterNoteRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'period_id' => ['required', 'uuid', 'exists:grade_periods,id'],
            'scale_data' => ['required', 'array', 'min:1'],
            'homeroom_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Siswa wajib dipilih',
            'student_id.exists' => 'Siswa tidak ditemukan di sistem',
            'period_id.required' => 'Periode penilaian wajib dipilih',
            'period_id.exists' => 'Periode penilaian tidak ditemukan di sistem',
            'scale_data.required' => 'Penilaian skala karakter pembiasaan wajib diisi',
            'scale_data.array' => 'Format skala karakter harus berupa array/objek',
            'homeroom_note.max' => 'Catatan wali kelas maksimal 2000 karakter',
        ];
    }
}
