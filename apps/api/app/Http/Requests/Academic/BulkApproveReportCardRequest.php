<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class BulkApproveReportCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:disetujui,terbit,draft,terkunci'],
            'report_ids' => ['nullable', 'array'],
            'report_ids.*' => ['uuid', 'exists:report_cards,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'semester_id' => ['nullable', 'uuid', 'exists:semesters,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->report_ids) && (empty($this->class_id) || empty($this->semester_id))) {
                $validator->errors()->add(
                    'target',
                    'Wajib menyertakan report_ids atau kombinasi class_id dan semester_id untuk pengesahan massal.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status pengesahan wajib diisi.',
            'status.in' => 'Status hanya boleh berupa disetujui, terbit, draft, atau terkunci.',
            'report_ids.array' => 'Daftar ID rapor harus berupa array.',
            'report_ids.*.uuid' => 'Format ID rapor tidak valid.',
            'class_id.uuid' => 'Format ID kelas tidak valid.',
            'semester_id.uuid' => 'Format ID semester tidak valid.',
            'note.max' => 'Catatan pengesahan maksimal 500 karakter.',
        ];
    }
}
