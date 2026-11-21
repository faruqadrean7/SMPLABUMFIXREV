<?php

namespace App\Http\Requests\Bk;

use Illuminate\Foundation\Http\FormRequest;

class ListCounselingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:pending,scheduled,rejected,completed,cancelled'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
