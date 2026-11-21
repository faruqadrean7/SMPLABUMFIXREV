<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLabEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['sometimes', 'required', 'uuid', 'exists:rooms,id'],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'stock' => ['sometimes', 'required', 'integer', 'min:1'],
            'condition' => ['nullable', 'string', 'in:baik,rusak_ringan,rusak_berat'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
