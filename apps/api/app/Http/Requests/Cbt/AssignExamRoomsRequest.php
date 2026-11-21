<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class AssignExamRoomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.room_id' => ['required', 'uuid', 'exists:rooms,id'],
            'rooms.*.proctor_id' => ['nullable', 'uuid', 'exists:users,id'],
            'rooms.*.session_no' => ['nullable', 'integer', 'min:1'],
            'rooms.*.token' => ['nullable', 'string', 'max:20'],
            'rooms.*.token_expires_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'rooms.required' => 'Daftar ruang ujian wajib disertakan.',
            'rooms.min' => 'Minimal 1 ruang ujian harus diplot.',
            'rooms.*.room_id.required' => 'Ruangan wajib dipilih.',
            'rooms.*.room_id.exists' => 'Data ruangan tidak ditemukan.',
            'rooms.*.proctor_id.exists' => 'Pengawas ujian tidak ditemukan.',
        ];
    }
}
