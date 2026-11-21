<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'parent_exam_id' => ['nullable', 'uuid', 'exists:exams,id'],
            'title' => ['required', 'string', 'max:200'],
            'exam_type' => ['required', 'string', 'in:kuis,harian,STS,SAS,ASAT,remedial,pengayaan'],
            'duration_min' => ['required', 'integer', 'min:5', 'max:360'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'passing_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'shuffle_options' => ['nullable', 'boolean'],
            'package_mode' => ['nullable', 'string', 'in:single,multi'],
            'show_result_mode' => ['nullable', 'string', 'in:never,after_submit,after_release'],
            'status' => ['nullable', 'string', 'in:draft,dijadwalkan,berlangsung,selesai,arsip'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject_id.required' => 'Mata pelajaran wajib dipilih.',
            'subject_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'title.required' => 'Judul ujian wajib diisi.',
            'title.max' => 'Judul ujian maksimal 200 karakter.',
            'exam_type.required' => 'Jenis ujian wajib dipilih.',
            'exam_type.in' => 'Jenis ujian harus salah satu dari: kuis, harian, STS, SAS, ASAT, remedial, pengayaan.',
            'duration_min.required' => 'Durasi pengerjaan ujian wajib diisi.',
            'duration_min.min' => 'Durasi pengerjaan minimal 5 menit.',
            'start_at.required' => 'Waktu mulai ujian wajib diisi.',
            'end_at.required' => 'Waktu selesai ujian wajib diisi.',
            'end_at.after' => 'Waktu selesai harus setelah waktu mulai ujian.',
        ];
    }
}
