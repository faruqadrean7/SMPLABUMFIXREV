<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class UpdateAssignmentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['sometimes', 'required', 'date'],
            'max_score' => ['nullable', 'numeric', 'between:1,100'],
            'grade_component_id' => ['nullable', 'uuid', 'exists:grade_components,id'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip', 'max:25600'],
            'file_id' => ['nullable', 'uuid', 'exists:files,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul tugas wajib diisi',
            'title.min' => 'Judul tugas minimal 3 karakter',
            'title.max' => 'Judul tugas maksimal 200 karakter',
            'due_at.required' => 'Batas waktu pengumpulan (deadline) tugas wajib diisi',
            'due_at.date' => 'Format batas waktu tidak valid',
            'max_score.between' => 'Nilai maksimal harus antara 1 sampai 100',
            'grade_component_id.exists' => 'Komponen nilai tidak ditemukan di sistem',
            'file.mimes' => 'Format berkas acuan tugas harus berupa PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, atau ZIP',
            'file.max' => 'Ukuran berkas acuan tugas maksimal 25 MB',
            'file_id.exists' => 'Berkas lampiran tidak ditemukan di sistem',
        ];
    }
}
