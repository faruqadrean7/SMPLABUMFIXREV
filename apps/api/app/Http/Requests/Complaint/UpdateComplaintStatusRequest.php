<?php

namespace App\Http\Requests\Complaint;

use App\Http\Requests\BaseApiRequest;

class UpdateComplaintStatusRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:ditinjau,ditindaklanjuti,selesai,ditolak'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'response_text' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status pengaduan wajib dipilih',
            'status.in' => 'Status pengaduan tidak valid (pilihan: ditinjau, ditindaklanjuti, selesai, ditolak)',
            'assigned_to.exists' => 'Petugas penangan tidak ditemukan di sistem',
            'response_text.required' => 'Catatan tanggapan/tindak lanjut wajib diisi',
            'response_text.min' => 'Catatan tanggapan minimal 3 karakter',
        ];
    }
}
