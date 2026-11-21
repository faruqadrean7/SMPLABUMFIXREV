<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;

class StorePostCategoryRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:post_categories,slug'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi',
            'slug.unique' => 'Slug kategori sudah digunakan',
        ];
    }
}
