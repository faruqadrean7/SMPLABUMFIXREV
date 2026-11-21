<?php

namespace App\Http\Requests\Bk;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCounselingCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'in:Pribadi,Sosial,Belajar,Karir,Lainnya'],
            'status' => ['nullable', 'string', 'in:aktif,selesai,dirujuk'],
            'summary' => ['nullable', 'string', 'min:5'],
        ];
    }
}
