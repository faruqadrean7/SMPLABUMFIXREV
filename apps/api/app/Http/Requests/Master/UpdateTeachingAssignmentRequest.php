<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class UpdateTeachingAssignmentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'teacher_id' => ['nullable', 'string', 'exists:teachers,id'],
            'hours_per_week' => ['nullable', 'integer', 'min:1', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.exists' => 'Guru pengampu tidak ditemukan di sistem',
            'hours_per_week.min' => 'Beban jam per minggu minimal 1 jam',
            'hours_per_week.max' => 'Beban jam per minggu maksimal 40 jam',
        ];
    }
}
