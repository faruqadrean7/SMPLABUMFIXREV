<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;
use App\Models\PPDBTrack;
use Illuminate\Validation\Rule;

class UpdatePPDBTrackRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id');
        $track = $id ? PPDBTrack::find($id) : null;
        $waveId = $track?->wave_id ?? $this->input('wave_id');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('ppdb_tracks', 'code')
                    ->where('wave_id', $waveId)
                    ->ignore($id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'quota' => ['sometimes', 'required', 'integer', 'min:1'],
            'score_formula' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode jalur pendaftaran wajib diisi',
            'code.unique' => 'Kode jalur sudah digunakan pada gelombang ini',
            'name.required' => 'Nama jalur pendaftaran wajib diisi',
            'quota.required' => 'Kuota pendaftaran wajib diisi',
            'quota.min' => 'Kuota pendaftaran minimal 1 siswa',
        ];
    }
}
