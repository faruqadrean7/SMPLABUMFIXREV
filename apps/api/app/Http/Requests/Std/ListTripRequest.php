<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class ListTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:direncanakan,dibuka,berlangsung,selesai,dibatalkan'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
