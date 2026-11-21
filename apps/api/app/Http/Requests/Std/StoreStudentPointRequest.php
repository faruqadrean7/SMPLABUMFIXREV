<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'point_type_id' => ['required', 'uuid', 'exists:point_types,id'],
            'point_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'evidence_file_id' => ['nullable', 'uuid', 'exists:files,id'],
        ];
    }
}
