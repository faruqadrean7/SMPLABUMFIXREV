<?php

namespace App\Http\Requests\Bk;

use Illuminate\Foundation\Http\FormRequest;

class HandleEarlyWarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:in_progress,resolved,dismissed'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status tindak lanjut wajib dipilih.',
            'status.in' => 'Status tindak lanjut harus salah satu dari: in_progress, resolved, dismissed.',
            'notes.max' => 'Catatan tindak lanjut maksimal 1000 karakter.',
        ];
    }
}
