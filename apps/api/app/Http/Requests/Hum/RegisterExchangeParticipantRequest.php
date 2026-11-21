<?php

namespace App\Http\Requests\Hum;

use Illuminate\Foundation\Http\FormRequest;

class RegisterExchangeParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'status' => ['nullable', 'string', 'in:terdaftar,lolos_seleksi,berangkat,selesai,mundur'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
