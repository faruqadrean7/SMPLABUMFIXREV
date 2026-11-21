<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAchievementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:verified,rejected'],
            'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:500'],
        ];
    }
}
