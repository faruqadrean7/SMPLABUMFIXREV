<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class StoreMaterialRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'class_id' => ['required', 'uuid', 'exists:classes,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,mp4', 'max:51200'],
            'file_id' => ['nullable', 'uuid', 'exists:files,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'class_id.required' => 'Kelas tujuan materi wajib dipilih',
            'class_id.exists' => 'Kelas tidak ditemukan di sistem',
            'subject_id.required' => 'Mata pelajaran wajib dipilih',
            'subject_id.exists' => 'Mata pelajaran tidak ditemukan di sistem',
            'title.required' => 'Judul materi wajib diisi',
            'title.min' => 'Judul materi minimal 3 karakter',
            'title.max' => 'Judul materi maksimal 200 karakter',
            'file.mimes' => 'Format berkas materi harus berupa PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, ZIP, atau MP4',
            'file.max' => 'Ukuran berkas materi maksimal 50 MB',
            'file_id.exists' => 'Berkas lampiran materi tidak ditemukan di sistem',
        ];
    }
}
