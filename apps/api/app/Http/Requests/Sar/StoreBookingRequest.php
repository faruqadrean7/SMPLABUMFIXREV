<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'uuid', 'exists:facilities,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'purpose' => ['required', 'string', 'max:1000'],
            'attendees_count' => ['nullable', 'integer', 'min:1'],
            'items' => ['nullable', 'array'],
            'items.*.facility_id' => ['required_with:items', 'uuid', 'exists:facilities,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
