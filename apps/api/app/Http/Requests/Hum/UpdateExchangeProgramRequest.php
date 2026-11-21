<?php

namespace App\Http\Requests\Hum;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExchangeProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partnership_id' => ['sometimes', 'required', 'uuid', 'exists:partnerships,id'],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:50'],
            'destination_country' => ['nullable', 'string', 'max:100'],
            'destination_institution' => ['nullable', 'string', 'max:200'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'pic_teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'status' => ['nullable', 'string', 'in:dibuka,berjalan,selesai,dibatalkan'],
        ];
    }
}
