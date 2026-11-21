<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'uuid', 'exists:rooms,id'],
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'stock' => ['required', 'integer', 'min:1'],
            'condition' => ['nullable', 'string', 'in:baik,rusak_ringan,rusak_berat'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
