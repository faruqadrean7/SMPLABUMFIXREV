<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class GradeSubmissionRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'between:0,100'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'score.required' => 'Nilai tugas wajib diisi',
            'score.numeric' => 'Nilai harus berupa angka desimal',
            'score.between' => 'Nilai harus berada dalam rentang 0 sampai 100',
            'feedback.max' => 'Catatan umpan balik maksimal 2000 karakter',
        ];
    }
}
