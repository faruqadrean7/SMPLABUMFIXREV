<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class RunAutoGradingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'uuid', 'exists:exam_sessions,id'],
            'force' => ['nullable', 'boolean'],
        ];
    }
}
