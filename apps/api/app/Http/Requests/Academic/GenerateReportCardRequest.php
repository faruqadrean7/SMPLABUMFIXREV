<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class GenerateReportCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'status' => ['nullable', 'string', 'in:draft,terkunci,disetujui'],
            'homeroom_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->student_id) && empty($this->class_id)) {
                $validator->errors()->add(
                    'target',
                    'Wajib menyertakan student_id atau class_id untuk generate e-rapor semester.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'semester_id.required' => 'Semester (semester_id) wajib diisi.',
            'semester_id.uuid' => 'Format ID semester tidak valid.',
            'semester_id.exists' => 'Data semester tidak ditemukan.',
            'student_id.uuid' => 'Format ID siswa tidak valid.',
            'student_id.exists' => 'Data siswa tidak ditemukan.',
            'class_id.uuid' => 'Format ID kelas tidak valid.',
            'class_id.exists' => 'Data kelas tidak ditemukan.',
            'status.in' => 'Status awal rapor tidak valid.',
            'homeroom_note.max' => 'Catatan wali kelas maksimal 1000 karakter.',
        ];
    }
}
