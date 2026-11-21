<?php

namespace App\Http\Requests\Bk;

use Illuminate\Foundation\Http\FormRequest;

class StoreCounselingCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'category' => ['required', 'string', 'in:Pribadi,Sosial,Belajar,Karir,Lainnya'],
            'status' => ['nullable', 'string', 'in:aktif,selesai,dirujuk'],
            'summary' => ['required', 'string', 'min:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'ID siswa wajib diisi.',
            'student_id.exists' => 'Data siswa tidak ditemukan.',
            'category.required' => 'Kategori bimbingan konseling wajib dipilih.',
            'category.in' => 'Kategori harus salah satu dari: Pribadi, Sosial, Belajar, Karir, Lainnya.',
            'summary.required' => 'Catatan ringkasan kasus wajib diisi.',
            'summary.min' => 'Catatan ringkasan kasus minimal 5 karakter.',
        ];
    }
}
