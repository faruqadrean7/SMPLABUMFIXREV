<?php

namespace App\Http\Requests\Sar;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'audit_date' => ['required', 'date'],
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
            'status' => ['nullable', 'string', 'in:draf,proses,selesai'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.asset_id' => ['required_with:items', 'uuid', 'exists:assets,id'],
            'items.*.is_present' => ['required_with:items', 'boolean'],
            'items.*.found_condition' => ['required_with:items', 'string', 'in:baik,rusak_ringan,rusak_berat'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
