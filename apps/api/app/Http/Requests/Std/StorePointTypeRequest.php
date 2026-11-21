<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class StorePointTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', 'in:pelanggaran,prestasi'],
            'name' => ['required', 'string', 'max:200'],
            'points' => ['required', 'integer', 'min:1'],
            'severity' => ['nullable', 'string', 'in:rendah,sedang,tinggi,berat'],
        ];
    }
}
