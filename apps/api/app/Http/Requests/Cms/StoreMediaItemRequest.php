<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;
use App\Models\MediaItem;
use Illuminate\Validation\Rule;

class StoreMediaItemRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'album_id' => ['required', 'uuid', 'exists:albums,id'],
            'file_id' => ['required', 'uuid', 'exists:files,id'],
            'media_type' => ['required', 'string', Rule::in(MediaItem::MEDIA_TYPES)],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'album_id.required' => 'ID album wajib diisi',
            'album_id.exists' => 'Album tidak ditemukan',
            'file_id.required' => 'ID file media wajib diisi',
            'file_id.exists' => 'File media tidak ditemukan',
            'media_type.required' => 'Tipe media wajib diisi',
            'media_type.in' => 'Tipe media harus berupa image atau video',
        ];
    }
}
