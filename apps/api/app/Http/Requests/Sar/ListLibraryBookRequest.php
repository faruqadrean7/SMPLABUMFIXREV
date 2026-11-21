<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class ListLibraryBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'book_type' => ['nullable', 'string', 'in:fisik,digital'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
