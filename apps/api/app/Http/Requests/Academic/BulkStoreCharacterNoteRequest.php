<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class BulkStoreCharacterNoteRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'period_id' => ['required', 'uuid', 'exists:grade_periods,id'],
            'notes' => ['required', 'array', 'min:1'],
            'notes.*.student_id' => ['required', 'uuid', 'exists:students,id'],
            'notes.*.scale_data' => ['required', 'array', 'min:1'],
            'notes.*.homeroom_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_id.required' => 'Periode penilaian wajib dipilih',
            'period_id.exists' => 'Periode penilaian tidak ditemukan di sistem',
            'notes.required' => 'Daftar catatan karakter wajib diisi',
            'notes.min' => 'Daftar catatan karakter minimal berisi 1 siswa',
            'notes.*.student_id.required' => 'ID siswa wajib diisi',
            'notes.*.student_id.exists' => 'Siswa tidak ditemukan di sistem',
            'notes.*.scale_data.required' => 'Skala karakter pembiasaan wajib diisi',
            'notes.*.homeroom_note.max' => 'Catatan wali kelas maksimal 2000 karakter',
        ];
    }
}
