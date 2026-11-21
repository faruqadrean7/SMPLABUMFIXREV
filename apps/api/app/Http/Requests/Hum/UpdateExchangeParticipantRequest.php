<?php

namespace App\Http\Requests\Hum;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExchangeParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', 'string', 'in:terdaftar,lolos_seleksi,berangkat,selesai,mundur'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'evaluation_notes' => ['nullable', 'string'],
        ];
    }
}
