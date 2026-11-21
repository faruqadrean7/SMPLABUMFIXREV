<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class ListProgressReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_id' => ['nullable', 'uuid', 'exists:grade_periods,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'status' => ['nullable', 'string', 'in:draft,menunggu_acc,disetujui,terbit'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
