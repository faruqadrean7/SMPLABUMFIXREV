<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class UpdatePPDBApplicationStepRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['nullable', 'string', 'max:200'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:L,P'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'parents' => ['nullable', 'array'],
            'parents.*.parent_type' => ['required_with:parents', 'string', 'in:ayah,ibu,wali'],
            'parents.*.name' => ['required_with:parents', 'string', 'max:200'],
            'parents.*.occupation' => ['nullable', 'string', 'max:100'],
            'parents.*.income_range' => ['nullable', 'string', 'max:100'],
            'parents.*.phone' => ['nullable', 'string', 'max:30'],
            'school' => ['nullable', 'array'],
            'school.name' => ['nullable', 'string', 'max:200'],
            'school.npsn' => ['nullable', 'string', 'max:20'],
            'school.address' => ['nullable', 'string'],
            'track_id' => ['nullable', 'uuid', 'exists:ppdb_tracks,id'],
            'major_choice' => ['nullable', 'string', 'max:100'],
            'data' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'gender.in' => 'Jenis kelamin harus L (Laki-laki) atau P (Perempuan)',
            'parents.*.parent_type.in' => 'Tipe orang tua harus salah satu dari: ayah, ibu, atau wali',
            'parents.*.name.required_with' => 'Nama orang tua/wali wajib diisi bila data orang tua disertakan',
            'track_id.exists' => 'Jalur pendaftaran yang dipilih tidak valid',
        ];
    }
}
