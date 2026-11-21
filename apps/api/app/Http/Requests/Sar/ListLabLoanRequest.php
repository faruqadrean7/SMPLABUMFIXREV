<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class ListLabLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:dipinjam,dikembalikan,terlambat'],
            'borrower_id' => ['nullable', 'uuid', 'exists:users,id'],
            'equipment_id' => ['nullable', 'uuid', 'exists:lab_equipment,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
