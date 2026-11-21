<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class AddClassStudentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'student_ids' => ['required_without:student_id', 'array'],
            'student_ids.*' => ['string', 'exists:students,id'],
            'student_id' => ['required_without:student_ids', 'string', 'exists:students,id'],
            'joined_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.required_without' => 'Pilih minimal satu siswa untuk dimasukkan ke kelas',
            'student_id.required_without' => 'Pilih minimal satu siswa untuk dimasukkan ke kelas',
        ];
    }
}
