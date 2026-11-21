<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTripConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:setuju,tolak'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
