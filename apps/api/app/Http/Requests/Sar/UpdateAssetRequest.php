<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $assetId = $this->route('id') ?? $this->route('asset');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'asset_code' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('assets', 'asset_code')->ignore($assetId)],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
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
