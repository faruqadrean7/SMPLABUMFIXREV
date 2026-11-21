<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class LockReportCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_locked' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $isLocked = filter_var($this->input('is_locked'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $reason = $this->input('reason');
            if ($isLocked === false && empty(trim((string) $reason))) {
                $validator->errors()->add('reason', 'Alasan wajib diisi saat membuka kunci rapor.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'is_locked.required' => 'Status penguncian (is_locked) wajib diisi.',
            'is_locked.boolean' => 'Format is_locked harus bernilai true atau false.',
            'reason.max' => 'Alasan kunci/buka kunci maksimal 255 karakter.',
        ];
    }
}
