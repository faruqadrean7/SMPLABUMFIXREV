<?php

namespace App\Http\Requests\Ntf;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'uuid'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
            'target_type' => ['sometimes', 'required', 'string', 'in:semua,kelas,tingkat,role'],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['string'],
            'attachment_file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'is_pinned' => ['nullable', 'boolean'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
