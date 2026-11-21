<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;
use App\Models\Post;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($id)],
            'content' => ['nullable', 'string'],
            'category_id' => ['nullable', 'uuid', 'exists:post_categories,id'],
            'cover_file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'status' => ['nullable', 'string', Rule::in(Post::STATUSES)],
            'visibility' => ['nullable', 'string', Rule::in(Post::VISIBILITIES)],
            'published_at' => ['nullable', 'date'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Slug berita sudah digunakan',
            'category_id.exists' => 'Kategori yang dipilih tidak valid',
            'cover_file_id.exists' => 'Berkas gambar sampul tidak ditemukan',
            'status.in' => 'Status berita harus draft, terbit, atau arsip',
            'visibility.in' => 'Visibilitas berita harus publik atau internal',
            'published_at.date' => 'Format tanggal tayang terjadwal tidak valid',
        ];
    }
}
