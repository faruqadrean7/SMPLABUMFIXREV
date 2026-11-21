<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class ApproveProgressReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:disetujui,terbit,draft,menunggu_acc'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status persetujuan wajib diisi.',
            'status.in' => 'Status hanya boleh berupa disetujui, terbit, draft (revisi), atau menunggu_acc.',
            'note.max' => 'Catatan persetujuan maksimal 500 karakter.',
        ];
    }
}
