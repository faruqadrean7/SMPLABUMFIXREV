<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class StartExamSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:20'],
            'device_fingerprint' => ['nullable', 'string', 'max:100'],
            'session_lock_token' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Token ruang ujian wajib diisi untuk memulai ujian.',
            'token.string' => 'Format token ruang ujian tidak valid.',
            'token.max' => 'Panjang token maksimal 20 karakter.',
        ];
    }
}
