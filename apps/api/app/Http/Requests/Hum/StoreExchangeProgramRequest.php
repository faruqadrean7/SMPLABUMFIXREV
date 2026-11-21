<?php

namespace App\Http\Requests\Hum;

use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partnership_id' => ['required', 'uuid', 'exists:partnerships,id'],
            'name' => ['required', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:50'],
            'destination_country' => ['nullable', 'string', 'max:100'],
            'destination_institution' => ['nullable', 'string', 'max:200'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pic_teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'status' => ['nullable', 'string', 'in:dibuka,berjalan,selesai,dibatalkan'],
        ];
    }
}
