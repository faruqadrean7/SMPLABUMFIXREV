<?php

namespace App\Http\Requests\Ntf;

use Illuminate\Foundation\Http\FormRequest;

class ListNotificationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['nullable', 'string', 'in:push,wa,whatsapp,email'],
            'status' => ['nullable', 'string', 'in:queued,sent,failed'],
            'recipient' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
