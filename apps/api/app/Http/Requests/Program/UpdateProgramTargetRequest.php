<?php

namespace App\Http\Requests\Program;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:50'],
            'target_value' => ['nullable', 'string', 'max:100'],
            'current_value' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'required', 'string', 'in:aktif,tercapai,batal'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul target capaian wajib diisi.',
            'status.in' => 'Status target harus salah satu dari: aktif, tercapai, batal.',
        ];
    }
}
