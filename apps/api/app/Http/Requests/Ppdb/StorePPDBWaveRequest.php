<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class StorePPDBWaveRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'open_at' => ['required', 'date'],
            'close_at' => ['required', 'date', 'after:open_at'],
            'result_publish_at' => ['required', 'date', 'after_or_equal:close_at'],
            'status' => ['required', 'string', 'in:draft,aktif,ditutup,selesai'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama gelombang PPDB wajib diisi',
            'open_at.required' => 'Tanggal buka pendaftaran wajib diisi',
            'close_at.required' => 'Tanggal tutup pendaftaran wajib diisi',
            'close_at.after' => 'Tanggal tutup pendaftaran harus setelah tanggal buka',
            'result_publish_at.required' => 'Tanggal pengumuman hasil wajib diisi',
            'result_publish_at.after_or_equal' => 'Tanggal pengumuman hasil tidak boleh sebelum tanggal tutup pendaftaran',
            'status.required' => 'Status gelombang wajib diisi',
            'status.in' => 'Status gelombang harus berupa draft, aktif, ditutup, atau selesai',
        ];
    }
}
