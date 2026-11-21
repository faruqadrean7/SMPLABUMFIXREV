<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class ListBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['nullable', 'uuid', 'exists:facilities,id'],
            'status' => ['nullable', 'string', 'in:diajukan,disetujui,ditolak,selesai,dibatalkan'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'search' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', 'string', 'in:list,calendar'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
