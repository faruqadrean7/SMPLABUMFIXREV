<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;
use App\Models\Page;
use Illuminate\Validation\Rule;

class StorePageRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'unique:pages,slug'],
            'content' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in(Page::STATUSES)],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul halaman wajib diisi',
            'slug.unique' => 'Slug halaman sudah digunakan',
            'content.required' => 'Konten halaman wajib diisi',
            'status.required' => 'Status halaman wajib diisi',
            'status.in' => 'Status halaman harus berupa draft atau terbit',
        ];
    }
}
