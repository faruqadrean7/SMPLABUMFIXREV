<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class LogExamViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'violation_type' => ['required', 'string', 'in:blur,window_blur,tab_switch,background,fullscreen_exit,inspect_element,other'],
            'meta' => ['nullable', 'array'],
            'details' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'violation_type.required' => 'Tipe pelanggaran wajib diisi.',
            'violation_type.in' => 'Tipe pelanggaran harus salah satu dari: blur, window_blur, tab_switch, background, fullscreen_exit, inspect_element, other.',
            'meta.array' => 'Data meta pelanggaran harus berupa array.',
            'details.array' => 'Data rincian pelanggaran harus berupa array.',
        ];
    }
}
