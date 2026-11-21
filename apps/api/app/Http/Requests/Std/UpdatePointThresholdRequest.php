<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePointThresholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'min_points' => ['sometimes', 'required', 'integer', 'min:1'],
            'action' => ['sometimes', 'required', 'string', 'max:200'],
            'notify_roles' => ['sometimes', 'required', 'array', 'min:1'],
            'notify_roles.*' => ['string', 'max:50'],
        ];
    }
}
