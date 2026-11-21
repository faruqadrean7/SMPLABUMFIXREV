<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateGuardianRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'full_name' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string', 'exists:users,id', Rule::unique('guardians', 'user_id')->ignore($id)],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'Akun user sudah terhubung dengan data orang tua/wali lain',
        ];
    }
}
