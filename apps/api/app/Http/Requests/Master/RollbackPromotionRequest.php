<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class RollbackPromotionRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'uuid', 'exists:students,id'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.required' => 'Daftar ID siswa yang akan di-rollback wajib disertakan',
            'student_ids.min' => 'Minimal 1 siswa harus dipilih untuk rollback',
            'student_ids.*.exists' => 'Data siswa tidak ditemukan',
            'reason.required' => 'Alasan pembatalan (rollback) wajib diisi',
        ];
    }
}
