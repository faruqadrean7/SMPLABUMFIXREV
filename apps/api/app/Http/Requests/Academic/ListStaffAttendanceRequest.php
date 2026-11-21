<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class ListStaffAttendanceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'in:hadir,terlambat,izin,sakit,alfa'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'Pengguna tidak ditemukan di sistem',
            'date_from.date_format' => 'Format tanggal awal harus YYYY-MM-DD',
            'date_to.date_format' => 'Format tanggal akhir harus YYYY-MM-DD',
            'date_to.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal',
            'status.in' => 'Status presensi tidak valid',
        ];
    }
}
