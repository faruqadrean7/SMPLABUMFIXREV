<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class ListPointTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['nullable', 'string', 'in:pelanggaran,prestasi'],
            'severity' => ['nullable', 'string', 'in:rendah,sedang,tinggi,berat'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
