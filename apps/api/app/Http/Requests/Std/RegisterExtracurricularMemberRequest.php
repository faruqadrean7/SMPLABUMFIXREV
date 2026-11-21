<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class RegisterExtracurricularMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'extracurricular_id' => ['required', 'uuid', 'exists:extracurriculars,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'role' => ['nullable', 'string', 'in:ketua,wakil_ketua,sekretaris,bendahara,anggota'],
        ];
    }
}
