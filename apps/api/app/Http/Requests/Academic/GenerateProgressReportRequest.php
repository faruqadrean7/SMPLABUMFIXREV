<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class GenerateProgressReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'uuid', 'exists:grade_periods,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'status' => ['nullable', 'string', 'in:draft,menunggu_acc'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->student_id) && empty($this->class_id)) {
                $validator->errors()->add(
                    'target',
                    'Wajib menyertakan student_id atau class_id untuk generate e-progress report.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'period_id.required' => 'Periode penilaian (period_id) wajib diisi.',
            'period_id.uuid' => 'Format ID periode tidak valid.',
            'period_id.exists' => 'Periode penilaian tidak ditemukan.',
            'student_id.uuid' => 'Format ID siswa tidak valid.',
            'student_id.exists' => 'Data siswa tidak ditemukan.',
            'class_id.uuid' => 'Format ID kelas tidak valid.',
            'class_id.exists' => 'Data kelas tidak ditemukan.',
            'status.in' => 'Status awal hanya boleh berupa draft atau menunggu_acc.',
        ];
    }
}
