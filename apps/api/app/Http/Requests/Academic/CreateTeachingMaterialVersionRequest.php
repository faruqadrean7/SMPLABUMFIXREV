<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class CreateTeachingMaterialVersionRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required_without:file_id', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip', 'max:25600'],
            'file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.min' => 'Judul perangkat ajar minimal 3 karakter',
            'title.max' => 'Judul perangkat ajar maksimal 200 karakter',
            'file.required_without' => 'Berkas revisi perangkat ajar wajib diunggah',
            'file.mimes' => 'Format berkas harus berupa PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, atau ZIP',
            'file.max' => 'Ukuran berkas perangkat ajar maksimal 25 MB',
            'file_id.exists' => 'Berkas berkas perangkat tidak ditemukan di sistem',
            'version.min' => 'Versi minimal 1',
        ];
    }
}
