<?php

namespace App\Http\Requests\Ntf;

use Illuminate\Foundation\Http\FormRequest;

class ListAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('published_only')) {
            $merge['published_only'] = filter_var($this->input('published_only'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if ($this->has('is_read')) {
            $merge['is_read'] = filter_var($this->input('is_read'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if ($this->has('is_pinned')) {
            $merge['is_pinned'] = filter_var($this->input('is_pinned'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'target_type' => ['nullable', 'string', 'in:semua,kelas,tingkat,role'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_read' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'published_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
