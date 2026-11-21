<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class ListAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
            'category' => ['nullable', 'string', 'max:100'],
            'asset_condition' => ['nullable', 'string', 'in:baik,rusak_ringan,rusak_berat'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
