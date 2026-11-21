<?php

namespace App\Http\Requests\Complaint;

use App\Http\Requests\BaseApiRequest;

class CreateComplaintRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'uuid'],
            'category' => ['required', 'string', 'in:akademik,fasilitas,bullying,pungli,pelayanan,lainnya'],
            'content' => ['required', 'string', 'min:10', 'max:5000'],
            'is_anonymous' => ['nullable', 'boolean'],
            'attachment_file_ids' => ['nullable', 'array', 'max:3'],
            'attachment_file_ids.*' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'Kategori aduan wajib dipilih',
            'category.in' => 'Kategori aduan tidak valid (pilihan: akademik, fasilitas, bullying, pungli, pelayanan, lainnya)',
            'content.required' => 'Uraian pengaduan wajib diisi',
            'content.min' => 'Uraian pengaduan minimal 10 karakter',
            'content.max' => 'Uraian pengaduan maksimal 5000 karakter',
            'attachment_file_ids.array' => 'Lampiran foto harus berupa daftar ID berkas',
            'attachment_file_ids.max' => 'Maksimal lampiran adalah 3 foto',
            'attachment_file_ids.*.required' => 'ID berkas lampiran tidak boleh kosong',
            'attachment_file_ids.*.uuid' => 'Format ID berkas lampiran tidak valid',
        ];
    }
}
