<?php

namespace App\Http\Requests\Guest;

use App\Http\Requests\BaseApiRequest;

class ListGuestRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,checked_in,checked_out'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'target_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status kunjungan tidak valid (pilihan: all, checked_in, checked_out)',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal',
            'target_user_id.exists' => 'Pejabat/guru tujuan tidak ditemukan',
            'per_page.min' => 'Jumlah per halaman minimal 1',
            'per_page.max' => 'Jumlah per halaman maksimal 100',
        ];
    }
}
