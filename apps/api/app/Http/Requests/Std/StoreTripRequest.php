<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'destination' => ['nullable', 'string', 'max:200'],
            'depart_date' => ['required', 'date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:depart_date'],
            'route' => ['nullable', 'array'],
            'guide_file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost_notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'in:direncanakan,dibuka,berlangsung,selesai,dibatalkan'],
        ];
    }
}
