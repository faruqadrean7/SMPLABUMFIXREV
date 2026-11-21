<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'facility_type' => ['sometimes', 'required', 'string', 'in:ruang,alat'],
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
            'location' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'requires_approval' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:tersedia,pemeliharaan,nonaktif'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }
}
