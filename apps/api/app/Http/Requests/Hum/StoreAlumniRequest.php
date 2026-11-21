<?php

namespace App\Http\Requests\Hum;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlumniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id', 'unique:alumni,student_id'],
            'graduation_year' => ['required', 'integer', 'min:1950', 'max:2100'],
            'current_status' => ['nullable', 'string', 'in:kuliah,bekerja,wirausaha,mencari_kerja,lainnya'],
            'next_institution' => ['nullable', 'string', 'max:200'],
            'study_program' => ['nullable', 'string', 'max:150'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'contact' => ['nullable', 'string', 'max:100'],
            'linkedin_url' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
        ];
    }
}
