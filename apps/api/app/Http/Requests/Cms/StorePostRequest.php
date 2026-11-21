<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;
use App\Models\Post;
use Illuminate\Validation\Rule;

class StorePostRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug'],
            'content' => ['required', 'string'],
            'category_id' => ['nullable', 'uuid', 'exists:post_categories,id'],
            'cover_file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'status' => ['required', 'string', Rule::in(Post::STATUSES)],
            'visibility' => ['required', 'string', Rule::in(Post::VISIBILITIES)],
            'published_at' => ['nullable', 'date'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul berita wajib diisi',
            'slug.unique' => 'Slug berita sudah digunakan',
            'content.required' => 'Konten berita wajib diisi',
            'category_id.exists' => 'Kategori yang dipilih tidak valid',
            'cover_file_id.exists' => 'Berkas gambar sampul tidak ditemukan',
            'status.required' => 'Status berita wajib ditentukan',
            'status.in' => 'Status berita harus draft, terbit, atau arsip',
            'visibility.required' => 'Visibilitas berita wajib ditentukan',
            'visibility.in' => 'Visibilitas berita harus publik atau internal',
            'published_at.date' => 'Format tanggal tayang terjadwal tidak valid',
        ];
    }
}
