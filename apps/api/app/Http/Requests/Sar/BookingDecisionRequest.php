<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class BookingDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:disetujui,ditolak,selesai'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
