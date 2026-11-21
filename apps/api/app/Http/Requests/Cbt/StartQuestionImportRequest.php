<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class StartQuestionImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'uuid', 'exists:question_banks,id'],
            'file' => ['required', 'file', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_id.required' => 'ID bank soal target wajib disertakan.',
            'bank_id.exists' => 'Bank soal tidak ditemukan.',
            'file.required' => 'Berkas template .docx wajib diunggah.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ];
    }
}
