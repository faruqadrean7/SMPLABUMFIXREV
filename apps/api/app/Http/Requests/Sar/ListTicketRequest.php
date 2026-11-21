<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class ListTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:pending,diproses,selesai'],
            'priority' => ['nullable', 'string', 'in:rendah,sedang,tinggi,darurat'],
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'reporter_id' => ['nullable', 'uuid', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
