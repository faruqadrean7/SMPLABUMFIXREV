<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class BulkLockReportCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_locked' => ['required', 'boolean'],
            'report_ids' => ['nullable', 'array'],
            'report_ids.*' => ['uuid', 'exists:report_cards,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'semester_id' => ['nullable', 'uuid', 'exists:semesters,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->report_ids) && (empty($this->class_id) || empty($this->semester_id))) {
                $validator->errors()->add(
                    'target',
                    'Wajib menyertakan report_ids atau kombinasi class_id dan semester_id untuk penguncian massal.'
                );
            }

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
            'is_locked.boolean' => 'Format is_locked harus bernilai boolean.',
            'report_ids.array' => 'Daftar ID rapor harus berupa array.',
            'report_ids.*.uuid' => 'Format ID rapor tidak valid.',
            'class_id.uuid' => 'Format ID kelas tidak valid.',
            'semester_id.uuid' => 'Format ID semester tidak valid.',
            'reason.max' => 'Alasan kunci/buka kunci maksimal 255 karakter.',
        ];
    }
}
