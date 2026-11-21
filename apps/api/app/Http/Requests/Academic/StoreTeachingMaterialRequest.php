<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class StoreTeachingMaterialRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'material_type' => ['required', 'string', 'in:modul,ATP,silabus,P5'],
            'grade_level_id' => ['nullable', 'uuid', 'exists:grade_levels,id'],
            'subject_id' => ['nullable', 'uuid', 'exists:subjects,id'],
            'file' => ['required_without:file_id', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip', 'max:25600'],
            'file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul perangkat ajar wajib diisi',
            'title.min' => 'Judul perangkat ajar minimal 3 karakter',
            'title.max' => 'Judul perangkat ajar maksimal 200 karakter',
            'material_type.required' => 'Jenis perangkat ajar wajib dipilih',
            'material_type.in' => 'Jenis perangkat harus salah satu dari: modul, ATP, silabus, P5',
            'grade_level_id.exists' => 'Tingkat kelas tidak ditemukan di sistem',
            'subject_id.exists' => 'Mata pelajaran tidak ditemukan di sistem',
            'file.required_without' => 'Berkas perangkat ajar wajib diunggah',
            'file.mimes' => 'Format berkas harus berupa PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, atau ZIP',
            'file.max' => 'Ukuran berkas perangkat ajar maksimal 25 MB',
            'file_id.exists' => 'Berkas berkas perangkat tidak ditemukan di sistem',
            'version.min' => 'Versi minimal 1',
        ];
    }
}
