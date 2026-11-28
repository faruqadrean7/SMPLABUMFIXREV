<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'nip' => ['nullable', 'string', 'max:50', Rule::unique('teachers', 'nip')->whereNull('deleted_at')->ignore($id)],
            'nuptk' => ['nullable', 'string', 'max:50', Rule::unique('teachers', 'nuptk')->whereNull('deleted_at')->ignore($id)],
            'full_name' => ['nullable', 'string', 'max:200'],
            'position' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:tetap,honorer,kontrak'],
            'photo_file_id' => ['nullable', 'string', 'exists:files,id'],
            'user_id' => ['nullable', 'string', 'exists:users,id', Rule::unique('teachers', 'user_id')->whereNull('deleted_at')->ignore($id)],
            'create_user_account' => ['nullable', 'boolean'],
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users', 'username')->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['nullable', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'nip.unique' => 'NIP sudah terdaftar di sistem',
            'nuptk.unique' => 'NUPTK sudah terdaftar di sistem',
            'status.in' => 'Status kepegawaian harus salah satu dari: tetap, honorer, kontrak',
            'user_id.unique' => 'Akun user sudah terhubung dengan guru lain',
        ];
    }
}
