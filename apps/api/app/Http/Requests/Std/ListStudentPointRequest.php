<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class ListStudentPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', 'string', 'in:pelanggaran,prestasi'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
