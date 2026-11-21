<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class RegenerateExamTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'validity_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
        ];
    }

    public function messages(): array
    {
        return [
            'validity_minutes.integer' => 'Durasi masa berlaku token harus berupa bilangan bulat.',
            'validity_minutes.min' => 'Masa berlaku token minimal 5 menit.',
            'validity_minutes.max' => 'Masa berlaku token maksimal 1440 menit (24 jam).',
        ];
    }
}
