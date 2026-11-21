<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class StorePPDBApplicationRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'wave_id' => ['nullable', 'uuid', 'exists:ppdb_waves,id'],
            'track_id' => ['required', 'uuid', 'exists:ppdb_tracks,id'],
            'nik' => ['required', 'string', 'size:16', 'regex:/^[0-9]{16}$/'],
            'full_name' => ['required', 'string', 'max:200'],
            'birth_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'wave_id.uuid' => 'Format ID gelombang harus berupa UUID',
            'wave_id.exists' => 'Gelombang PPDB tidak ditemukan',
            'track_id.required' => 'Jalur pendaftaran wajib dipilih',
            'track_id.uuid' => 'Format ID jalur harus berupa UUID',
            'track_id.exists' => 'Jalur pendaftaran tidak ditemukan',
            'nik.required' => 'NIK calon siswa wajib diisi',
            'nik.size' => 'NIK harus berjumlah 16 digit angka',
            'nik.regex' => 'NIK harus berupa 16 digit angka numerik',
            'full_name.required' => 'Nama lengkap calon siswa wajib diisi',
            'full_name.max' => 'Nama lengkap maksimal 200 karakter',
            'birth_date.required' => 'Tanggal lahir calon siswa wajib diisi',
            'birth_date.date' => 'Format tanggal lahir tidak valid',
        ];
    }
}
