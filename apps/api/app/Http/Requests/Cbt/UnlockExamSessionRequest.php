<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class UnlockExamSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:unlock,extra_time,reset,force_submit'],
            'extra_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Aksi pengawas wajib ditentukan.',
            'action.in' => 'Aksi pengawas harus salah satu dari: unlock, extra_time, reset, force_submit.',
            'extra_minutes.integer' => 'Tambahan waktu harus berupa bilangan bulat menit.',
            'extra_minutes.min' => 'Tambahan waktu minimal 1 menit.',
            'extra_minutes.max' => 'Tambahan waktu maksimal 180 menit.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}
