<?php

namespace App\Http\Requests\Rpt;

use Illuminate\Foundation\Http\FormRequest;

class ReviewApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:approve,reject,disetujui,ditolak'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'rejectionReason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $action = $this->input('action');
            $notes = $this->input('notes') ?? $this->input('rejectionReason');
            if (in_array($action, ['reject', 'ditolak'], true) && empty(trim((string) $notes))) {
                $validator->errors()->add('notes', 'Alasan penolakan wajib diisi.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Aksi persetujuan wajib diisi.',
            'action.in' => 'Aksi harus berupa approve atau reject.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
