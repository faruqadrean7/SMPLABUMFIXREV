<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class DailyGateAttendanceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'att_date' => ['required', 'date', 'date_format:Y-m-d'],
            'status' => ['required', 'string', 'in:hadir,izin,sakit,alpa,terlambat'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'ID siswa wajib diisi',
            'student_id.exists' => 'Siswa tidak ditemukan di sistem',
            'att_date.required' => 'Tanggal presensi wajib diisi',
            'att_date.date_format' => 'Format tanggal harus YYYY-MM-DD',
            'status.required' => 'Status presensi wajib diisi',
            'status.in' => 'Status presensi harus salah satu dari: hadir, izin, sakit, alpa, terlambat',
        ];
    }
}
