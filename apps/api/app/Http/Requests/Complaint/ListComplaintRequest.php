<?php

namespace App\Http\Requests\Complaint;

use App\Http\Requests\BaseApiRequest;

class ListComplaintRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string'],
            'status' => ['nullable', 'in:all,baru,ditinjau,ditindaklanjuti,selesai,ditolak'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status pengaduan tidak valid (pilihan: all, baru, ditinjau, ditindaklanjuti, selesai, ditolak)',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal',
            'assigned_to.exists' => 'Petugas penangan tidak ditemukan',
            'per_page.min' => 'Jumlah per halaman minimal 1',
            'per_page.max' => 'Jumlah per halaman maksimal 100',
        ];
    }
}
