<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;

class UpdateNavigationMenuRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.title' => ['required', 'string', 'max:100'],
            'items.*.url' => ['required', 'string', 'max:255'],
            'items.*.target' => ['nullable', 'string', 'in:_self,_blank'],
            'items.*.is_active' => ['nullable', 'boolean'],
            'items.*.children' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Daftar item menu wajib diisi',
            'items.*.title.required' => 'Judul item menu wajib diisi',
            'items.*.url.required' => 'Tautan URL item menu wajib diisi',
        ];
    }
}
