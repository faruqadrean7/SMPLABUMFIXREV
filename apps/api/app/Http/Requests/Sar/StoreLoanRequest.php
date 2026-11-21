<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'uuid', 'exists:library_books,id'],
            'borrower_id' => ['nullable', 'uuid', 'exists:users,id'],
            'loan_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:loan_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
