<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class CorrectStaffAttendanceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:hadir,terlambat,izin,sakit,alfa'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status presensi koreksi wajib dipilih',
            'status.in' => 'Status presensi harus salah satu dari: hadir, terlambat, izin, sakit, alfa',
            'reason.required' => 'Alasan perbaikan/koreksi presensi wajib diisi',
            'reason.min' => 'Alasan perbaikan presensi minimal 3 karakter',
            'reason.max' => 'Alasan perbaikan presensi maksimal 500 karakter',
        ];
    }
}
