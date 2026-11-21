<?php

namespace App\Http\Requests\Bk;

use Illuminate\Foundation\Http\FormRequest;

class ListEarlyWarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved,dismissed'],
            'severity' => ['nullable', 'string', 'in:rendah,sedang,tinggi'],
            'rule_code' => ['nullable', 'string', 'max:100'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'class_id' => ['nullable', 'uuid', 'exists:school_classes,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
