<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class SubmitAssignmentRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('fileId') && ! $this->has('file_id')) {
            $merge['file_id'] = $this->input('fileId');
        }
        if ($this->has('fileName') && ! $this->has('file_name')) {
            $merge['file_name'] = $this->input('fileName');
        }
        if ($this->has('driveLink') && ! $this->has('drive_link')) {
            $merge['drive_link'] = $this->input('driveLink');
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'file' => ['required_without_all:file_id,drive_link,notes,file_name', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,jpg,jpeg,png', 'max:25600'],
            'file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'drive_link' => ['nullable', 'url', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required_without_all' => 'Berkas pengerjaan, tautan berkas/drive, atau catatan tugas wajib disertakan',
            'file.mimes' => 'Format berkas pengumpulan harus berupa PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, ZIP, atau Gambar',
            'file.max' => 'Ukuran berkas pengumpulan maksimal 25 MB',
            'file_id.exists' => 'Berkas pengerjaan tidak ditemukan di sistem',
            'drive_link.url' => 'Tautan tugas/drive harus berupa format URL yang valid',
            'notes.max' => 'Catatan siswa maksimal 1000 karakter',
        ];
    }
}
