<?php

namespace App\Http\Requests\Hum;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTracerResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alumni_id' => ['nullable', 'uuid', 'exists:alumni,id'],
            'answers' => ['required', 'array', 'min:1'],
        ];
    }
}
