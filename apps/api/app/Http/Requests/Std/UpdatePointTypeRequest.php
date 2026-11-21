<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePointTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['sometimes', 'required', 'string', 'in:pelanggaran,prestasi'],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'points' => ['sometimes', 'required', 'integer', 'min:1'],
            'severity' => ['nullable', 'string', 'in:rendah,sedang,tinggi,berat'],
        ];
    }
}
