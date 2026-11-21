<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class StorePointThresholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'min_points' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'string', 'max:200'],
            'notify_roles' => ['required', 'array', 'min:1'],
            'notify_roles.*' => ['string', 'max:50'],
        ];
    }
}
