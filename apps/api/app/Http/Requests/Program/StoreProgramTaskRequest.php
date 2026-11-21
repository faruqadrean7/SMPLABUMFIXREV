<?php

namespace App\Http\Requests\Program;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'program_type' => ['required', 'string', 'in:reguler,tahfidz,icp,inklusi'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'target_scope' => ['nullable', 'string', 'in:all,class,student'],
            'assigned_student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'meta' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'program_type.required' => 'Jenis program wajib dipilih.',
            'program_type.in' => 'Jenis program harus salah satu dari: reguler, tahfidz, icp, inklusi.',
            'title.required' => 'Judul tugas wajib diisi.',
            'due_at.date' => 'Format batas waktu penugasan tidak valid.',
        ];
    }
}
