<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;
use App\Models\MediaItem;
use Illuminate\Validation\Rule;

class UpdateMediaItemRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'media_type' => ['sometimes', 'required', 'string', Rule::in(MediaItem::MEDIA_TYPES)],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'media_type.in' => 'Tipe media harus berupa image atau video',
        ];
    }
}
