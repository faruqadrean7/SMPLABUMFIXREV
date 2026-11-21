<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class StorePPDBTrackRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $waveId = $this->input('wave_id');

        return [
            'wave_id' => ['required', 'uuid', 'exists:ppdb_waves,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('ppdb_tracks', 'code')->where(function ($query) use ($waveId) {
                    return $query->where('wave_id', $waveId);
                }),
            ],
            'name' => ['required', 'string', 'max:100'],
            'quota' => ['required', 'integer', 'min:1'],
            'score_formula' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'wave_id.required' => 'Gelombang PPDB wajib dipilih',
            'wave_id.exists' => 'Gelombang PPDB tidak ditemukan',
            'code.required' => 'Kode jalur pendaftaran wajib diisi',
            'code.unique' => 'Kode jalur sudah digunakan pada gelombang ini',
            'name.required' => 'Nama jalur pendaftaran wajib diisi',
            'quota.required' => 'Kuota pendaftaran wajib diisi',
            'quota.min' => 'Kuota pendaftaran minimal 1 siswa',
        ];
    }
}
