<?php

namespace App\Http\Requests\Program;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'program_type' => ['required', 'string', 'in:reguler,tahfidz,icp,inklusi'],
            'title' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:50'],
            'target_value' => ['nullable', 'string', 'max:100'],
            'current_value' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:aktif,tercapai,batal'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'ID siswa sasaran wajib diisi.',
            'student_id.exists' => 'Data siswa sasaran tidak ditemukan.',
            'program_type.required' => 'Jenis program wajib dipilih.',
            'program_type.in' => 'Jenis program harus salah satu dari: reguler, tahfidz, icp, inklusi.',
            'title.required' => 'Judul target capaian wajib diisi.',
            'status.in' => 'Status target harus salah satu dari: aktif, tercapai, batal.',
        ];
    }
}
