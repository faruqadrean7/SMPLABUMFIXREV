<?php

namespace App\Http\Requests\Rpt;

use Illuminate\Foundation\Http\FormRequest;

class ExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'format' => ['nullable', 'string', 'in:csv,xlsx,pdf'],
        ];
    }
}
