<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class UpdateGradeComponentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'weight' => ['sometimes', 'required', 'numeric', 'between:0.01,100'],
            'code' => ['sometimes', 'required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama komponen nilai wajib diisi',
            'weight.required' => 'Bobot komponen nilai wajib diisi',
            'weight.between' => 'Bobot komponen nilai harus berada di antara 0.01 sampai 100',
            'code.required' => 'Kode komponen nilai wajib diisi',
        ];
    }
}
