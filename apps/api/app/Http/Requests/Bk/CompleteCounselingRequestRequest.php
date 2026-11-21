<?php

namespace App\Http\Requests\Bk;

use Illuminate\Foundation\Http\FormRequest;

class CompleteCounselingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'create_case' => ['nullable', 'boolean'],
            'case_category' => ['nullable', 'string', 'in:Pribadi,Sosial,Belajar,Karir,Lainnya'],
            'case_summary' => ['nullable', 'string', 'max:2000'],
            'session_notes' => ['nullable', 'string', 'max:2000'],
            'follow_up' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
