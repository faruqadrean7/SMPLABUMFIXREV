<?php

namespace App\Http\Requests\Program;

use App\Http\Requests\BaseApiRequest;

class SubmitProgramTaskRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'file_id' => ['nullable', 'uuid', 'required_without_all:link,notes'],
            'link' => ['nullable', 'url', 'max:500', 'required_without_all:file_id,notes'],
            'notes' => ['nullable', 'string', 'max:2000', 'required_without_all:file_id,link'],
        ];
    }

    public function messages(): array
    {
        return [
            'file_id.uuid' => 'Format file_id harus berupa UUID valid.',
            'link.url' => 'Tautan pengumpulan harus berupa format URL valid.',
            'link.max' => 'Tautan maksimal 500 karakter.',
            'notes.max' => 'Catatan maksimal 2000 karakter.',
            'file_id.required_without_all' => 'Harap sertakan file, tautan, atau catatan jawaban untuk mengumpulkan tugas.',
            'link.required_without_all' => 'Harap sertakan file, tautan, atau catatan jawaban untuk mengumpulkan tugas.',
            'notes.required_without_all' => 'Harap sertakan file, tautan, atau catatan jawaban untuk mengumpulkan tugas.',
        ];
    }
}
