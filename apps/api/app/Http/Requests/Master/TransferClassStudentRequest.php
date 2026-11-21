<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class TransferClassStudentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'string', 'exists:students,id'],
            'target_class_id' => ['required', 'string', 'exists:classes,id'],
            'transfer_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'ID siswa yang akan dipindahkan wajib diisi',
            'target_class_id.required' => 'Kelas tujuan wajib dipilih',
            'target_class_id.exists' => 'Kelas tujuan tidak ditemukan di sistem',
            'transfer_date.required' => 'Tanggal kepindahan wajib diisi',
        ];
    }
}
