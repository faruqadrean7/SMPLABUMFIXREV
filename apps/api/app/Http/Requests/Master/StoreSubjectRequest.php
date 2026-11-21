<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class StoreSubjectRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:subjects,code'],
            'name' => ['required', 'string', 'max:150'],
            'subject_group' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode mata pelajaran wajib diisi',
            'code.unique' => 'Kode mata pelajaran sudah digunakan',
            'name.required' => 'Nama mata pelajaran wajib diisi',
            'subject_group.required' => 'Kelompok mata pelajaran wajib diisi (misal: Kelompok A, Kelompok B)',
        ];
    }
}
