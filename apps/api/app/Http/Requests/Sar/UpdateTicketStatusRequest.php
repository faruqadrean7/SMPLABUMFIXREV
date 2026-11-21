<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:diproses,selesai,pending'],
            'note' => ['nullable', 'string', 'max:1000'],
            'file_id' => ['nullable', 'uuid', 'exists:files,id'],
        ];
    }
}
