<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'full_name' => ['nullable', 'string', 'max:200'],
            'position' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'string', 'exists:users,id', Rule::unique('staff', 'user_id')->ignore($id)],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'Akun user sudah terhubung dengan staf lain',
        ];
    }
}
