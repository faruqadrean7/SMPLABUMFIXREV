<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class DisposeTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignee_id' => ['required', 'uuid', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
