<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'code' => ['nullable', 'string', 'max:30', Rule::unique('subjects', 'code')->ignore($id)],
            'name' => ['nullable', 'string', 'max:150'],
            'subject_group' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Kode mata pelajaran sudah digunakan',
        ];
    }
}
