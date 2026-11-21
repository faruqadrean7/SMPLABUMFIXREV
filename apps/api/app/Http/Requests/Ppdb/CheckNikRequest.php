<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class CheckNikRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'nik' => ['required', 'string', 'size:16', 'regex:/^[0-9]{16}$/'],
            'wave_id' => ['nullable', 'uuid', 'exists:ppdb_waves,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.required' => 'NIK wajib diisi',
            'nik.size' => 'NIK harus berjumlah 16 digit angka',
            'nik.regex' => 'NIK harus berupa 16 digit angka numerik',
            'wave_id.uuid' => 'ID gelombang harus berupa UUID yang valid',
            'wave_id.exists' => 'Gelombang PPDB yang dipilih tidak ditemukan',
        ];
    }
}
