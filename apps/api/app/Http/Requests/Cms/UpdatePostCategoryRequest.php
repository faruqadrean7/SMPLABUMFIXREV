<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdatePostCategoryRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => ['nullable', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('post_categories', 'slug')->ignore($id)],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Slug kategori sudah digunakan',
        ];
    }
}
