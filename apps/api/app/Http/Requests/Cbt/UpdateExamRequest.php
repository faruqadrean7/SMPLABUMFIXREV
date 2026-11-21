<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['nullable', 'uuid', 'exists:subjects,id'],
            'parent_exam_id' => ['nullable', 'uuid', 'exists:exams,id'],
            'title' => ['nullable', 'string', 'max:200'],
            'exam_type' => ['nullable', 'string', 'in:kuis,harian,STS,SAS,ASAT,remedial,pengayaan'],
            'duration_min' => ['nullable', 'integer', 'min:5', 'max:360'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
            'passing_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'shuffle_options' => ['nullable', 'boolean'],
            'package_mode' => ['nullable', 'string', 'in:single,multi'],
            'show_result_mode' => ['nullable', 'string', 'in:never,after_submit,after_release'],
            'status' => ['nullable', 'string', 'in:draft,dijadwalkan,berlangsung,selesai,arsip'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('start_at') && $this->filled('end_at')) {
                if (strtotime($this->end_at) <= strtotime($this->start_at)) {
                    $validator->errors()->add('end_at', 'Waktu selesai harus setelah waktu mulai ujian.');
                }
            }
        });
    }
}
