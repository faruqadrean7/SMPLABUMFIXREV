<?php

namespace App\Http\Requests\Bk;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCounselingReferralStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,diterima,ditolak,selesai'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
