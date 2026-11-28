<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'position' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'string', 'exists:users,id', Rule::unique('staff', 'user_id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap staf wajib diisi',
            'user_id.unique' => 'Akun user sudah terhubung dengan staf lain',
        ];
    }
}
