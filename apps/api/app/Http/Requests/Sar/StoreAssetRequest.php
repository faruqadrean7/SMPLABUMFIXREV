<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'asset_code' => ['required', 'string', 'max:100', 'unique:assets,asset_code'],
            'category' => ['required', 'string', 'max:100'],
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'asset_condition' => ['nullable', 'string', 'in:baik,rusak_ringan,rusak_berat'],
            'acquired_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
