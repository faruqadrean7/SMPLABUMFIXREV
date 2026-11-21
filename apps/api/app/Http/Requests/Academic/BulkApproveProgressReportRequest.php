<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class BulkApproveProgressReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:disetujui,terbit,draft,menunggu_acc'],
            'report_ids' => ['nullable', 'array'],
            'report_ids.*' => ['uuid', 'exists:progress_reports,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'period_id' => ['nullable', 'uuid', 'exists:grade_periods,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->report_ids) && (empty($this->class_id) || empty($this->period_id))) {
                $validator->errors()->add(
                    'target',
                    'Wajib menyertakan report_ids atau kombinasi class_id dan period_id untuk persetujuan massal.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status persetujuan wajib diisi.',
            'status.in' => 'Status hanya boleh berupa disetujui, terbit, atau draft (revisi).',
            'report_ids.array' => 'Daftar ID rapor harus berupa array.',
            'report_ids.*.uuid' => 'Format ID rapor tidak valid.',
            'class_id.uuid' => 'Format ID kelas tidak valid.',
            'period_id.uuid' => 'Format ID periode tidak valid.',
            'note.max' => 'Catatan persetujuan maksimal 500 karakter.',
        ];
    }
}
