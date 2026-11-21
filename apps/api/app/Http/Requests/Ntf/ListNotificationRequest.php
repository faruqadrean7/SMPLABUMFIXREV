<?php

namespace App\Http\Requests\Ntf;

use Illuminate\Foundation\Http\FormRequest;

class ListNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_read') && $this->is_read !== null) {
            $this->merge([
                'is_read' => filter_var($this->is_read, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'is_read' => ['nullable', 'boolean'],
            'notif_type' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
