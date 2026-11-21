<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => ['nullable', 'string', 'max:50', Rule::unique('academic_years', 'name')->ignore($id)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Nama tahun ajaran sudah terdaftar',
        ];
    }
}
