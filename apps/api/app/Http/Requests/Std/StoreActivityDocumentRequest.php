<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'extracurricular_id' => ['required', 'uuid', 'exists:extracurriculars,id'],
            'file_id' => ['required', 'uuid', 'exists:files,id'],
            'doc_type' => ['required', 'string', 'in:proposal,laporan'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
