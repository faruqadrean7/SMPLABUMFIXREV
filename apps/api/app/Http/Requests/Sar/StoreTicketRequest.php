<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $desc = $this->input('description', '');
        $category = $this->input('category');
        $location = $this->input('location');
        $prefix = [];
        if ($category) {
            $prefix[] = "[Kategori: {$category}]";
        }
        if ($location) {
            $prefix[] = "[Lokasi: {$location}]";
        }
        if (! empty($prefix) && is_string($desc)) {
            $prefixStr = implode(' ', $prefix);
            if (! str_contains($desc, $prefixStr)) {
                $this->merge([
                    'description' => trim("{$prefixStr}\n{$desc}"),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'description' => ['required', 'string', 'max:2500'],
            'priority' => ['nullable', 'string', 'in:rendah,sedang,tinggi,darurat'],
            'photo_file_id' => ['nullable', 'uuid', 'exists:files,id'],
        ];
    }
}
