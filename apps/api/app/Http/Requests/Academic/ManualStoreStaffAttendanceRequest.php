<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class ManualStoreStaffAttendanceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'att_date' => ['required', 'date', 'date_format:Y-m-d'],
            'status' => ['required', 'string', 'in:hadir,terlambat,izin,sakit,alfa'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Pegawai/guru wajib dipilih',
            'user_id.exists' => 'Pegawai tidak ditemukan di sistem',
            'att_date.required' => 'Tanggal presensi wajib diisi',
            'att_date.date_format' => 'Format tanggal presensi harus YYYY-MM-DD',
            'status.required' => 'Status kehadiran wajib dipilih',
            'status.in' => 'Status presensi harus salah satu dari: hadir, terlambat, izin, sakit, alfa',
            'notes.max' => 'Catatan presensi maksimal 500 karakter',
        ];
    }
}
