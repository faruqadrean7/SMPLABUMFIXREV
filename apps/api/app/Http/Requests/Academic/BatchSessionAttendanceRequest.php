<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class BatchSessionAttendanceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'journal_id' => ['required', 'uuid', 'exists:journals,id'],
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.student_id' => ['required', 'uuid', 'exists:students,id'],
            'attendances.*.status' => ['required', 'string', 'in:hadir,izin,sakit,alpa,terlambat'],
            'attendances.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'journal_id.required' => 'Jurnal sesi mengajar wajib dipilih',
            'journal_id.exists' => 'Jurnal sesi mengajar tidak ditemukan',
            'attendances.required' => 'Daftar presensi siswa wajib diisi',
            'attendances.min' => 'Daftar presensi minimal berisi 1 siswa',
            'attendances.*.student_id.required' => 'ID siswa wajib diisi',
            'attendances.*.student_id.exists' => 'Siswa tidak ditemukan di sistem',
            'attendances.*.status.required' => 'Status kehadiran siswa wajib diisi',
            'attendances.*.status.in' => 'Status kehadiran harus salah satu dari: hadir, izin, sakit, alpa, terlambat',
        ];
    }
}
