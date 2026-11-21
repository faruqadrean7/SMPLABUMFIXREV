<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class StoreLibraryBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:200'],
            'publisher' => ['nullable', 'string', 'max:200'],
            'publish_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'isbn' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'book_type' => ['nullable', 'string', 'in:fisik,digital'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'cover_file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'location_shelf' => ['nullable', 'string', 'max:100'],
        ];
    }
}
