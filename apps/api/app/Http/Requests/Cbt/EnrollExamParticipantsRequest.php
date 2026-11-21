<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class EnrollExamParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_room_id' => ['required', 'uuid', 'exists:exam_rooms,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['uuid', 'exists:students,id'],
            'package_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->class_id) && empty($this->student_ids)) {
                $validator->errors()->add('target', 'Wajib memilih class_id atau daftar student_ids untuk mendaftarkan peserta ujian.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'exam_room_id.required' => 'Ruang ujian (exam_room_id) wajib dipilih.',
            'exam_room_id.exists' => 'Ruang ujian tidak ditemukan.',
            'class_id.exists' => 'Kelas tidak ditemukan.',
        ];
    }
}
