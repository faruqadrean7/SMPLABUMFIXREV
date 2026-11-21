<?php

namespace App\Http\Requests\Hum;

use Illuminate\Foundation\Http\FormRequest;

class ListExchangeProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partnership_id' => ['nullable', 'uuid', 'exists:partnerships,id'],
            'status' => ['nullable', 'string', 'in:dibuka,berjalan,selesai,dibatalkan'],
            'type' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
