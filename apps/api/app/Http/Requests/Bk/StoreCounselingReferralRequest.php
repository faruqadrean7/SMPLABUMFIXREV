<?php

namespace App\Http\Requests\Bk;

use Illuminate\Foundation\Http\FormRequest;

class StoreCounselingReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'reason' => ['required', 'string', 'min:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'ID siswa wajib diisi.',
            'student_id.exists' => 'Data siswa tidak ditemukan.',
            'reason.required' => 'Alasan rujukan bimbingan konseling wajib diisi.',
            'reason.min' => 'Alasan rujukan minimal 5 karakter.',
        ];
    }
}
