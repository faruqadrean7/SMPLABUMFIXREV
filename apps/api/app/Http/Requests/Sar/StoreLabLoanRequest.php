<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment_id' => ['required', 'uuid', 'exists:lab_equipment,id'],
            'borrower_id' => ['nullable', 'uuid', 'exists:users,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'loan_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:loan_date'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
