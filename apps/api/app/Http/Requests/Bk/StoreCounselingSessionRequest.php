<?php

namespace App\Http\Requests\Bk;

use Illuminate\Foundation\Http\FormRequest;

class StoreCounselingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_date' => ['required', 'date'],
            'notes' => ['required', 'string', 'min:5'],
            'follow_up' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'session_date.required' => 'Tanggal sesi konseling wajib diisi.',
            'session_date.date' => 'Format tanggal sesi konseling tidak valid.',
            'notes.required' => 'Catatan sesi konseling wajib diisi.',
            'notes.min' => 'Catatan sesi konseling minimal 5 karakter.',
        ];
    }
}
