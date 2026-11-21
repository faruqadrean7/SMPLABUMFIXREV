<?php

namespace App\Http\Requests\Hum;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'uuid', 'exists:schools,id'],
            'partner_name' => ['required', 'string', 'max:200'],
            'mou_number' => ['nullable', 'string', 'max:100'],
            'partner_type' => ['nullable', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'scope' => ['required', 'string'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'status' => ['nullable', 'string', 'in:draft,aktif,kedaluwarsa,dihentikan'],
            'notes' => ['nullable', 'string'],
            'mou_file_id' => ['nullable', 'uuid', 'exists:files,id'],
        ];
    }
}
