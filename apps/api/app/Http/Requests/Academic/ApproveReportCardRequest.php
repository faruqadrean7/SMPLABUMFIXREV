<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class ApproveReportCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:disetujui,terbit,draft,ditolak'],
            'note' => ['nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $status = $this->input('status');
            $note = $this->input('note') ?? $this->input('reason');
            if (in_array($status, ['draft', 'ditolak'], true) && empty(trim((string) $note))) {
                $validator->errors()->add('note', 'Catatan / alasan penolakan wajib diisi saat menolak atau meminta revisi rapor.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status persetujuan wajib diisi.',
            'status.in' => 'Status hanya boleh berupa disetujui, terbit, draft (revisi), atau ditolak.',
            'note.max' => 'Catatan persetujuan maksimal 500 karakter.',
        ];
    }
}
