<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class ListExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['nullable', 'uuid', 'exists:subjects,id'],
            'exam_type' => ['nullable', 'string', 'in:kuis,harian,STS,SAS,ASAT,remedial,pengayaan'],
            'status' => ['nullable', 'string', 'in:draft,dijadwalkan,berlangsung,selesai,arsip'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
