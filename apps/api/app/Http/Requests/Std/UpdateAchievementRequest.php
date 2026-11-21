<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAchievementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['sometimes', 'required', 'uuid', 'exists:students,id'],
            'competition_id' => ['nullable', 'uuid', 'exists:competitions,id'],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'rank_level' => ['sometimes', 'required', 'string', 'max:100'],
            'achieved_at' => ['sometimes', 'required', 'date'],
            'certificate_file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'photo_file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'points_awarded' => ['nullable', 'integer', 'min:0'],
            'point_type_id' => ['nullable', 'uuid', 'exists:point_types,id'],
            'verification_status' => ['nullable', 'string', 'in:pending,verified,rejected'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
