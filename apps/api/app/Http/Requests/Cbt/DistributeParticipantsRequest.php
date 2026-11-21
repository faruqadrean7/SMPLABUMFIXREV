<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class DistributeParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['nullable', 'string', 'in:balanced,fill_capacity'],
            'exam_room_ids' => ['nullable', 'array'],
            'exam_room_ids.*' => ['uuid', 'exists:exam_rooms,id'],
            'custom_capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'mode.in' => 'Mode distribusi peserta harus berupa "balanced" atau "fill_capacity".',
            'exam_room_ids.array' => 'Daftar ID ruang ujian harus berupa array.',
            'exam_room_ids.*.uuid' => 'ID ruang ujian harus berformat UUID yang valid.',
            'exam_room_ids.*.exists' => 'Salah satu ID ruang ujian tidak ditemukan.',
            'custom_capacity.integer' => 'Kapasitas kustom harus berupa bilangan bulat.',
            'custom_capacity.min' => 'Kapasitas kustom minimal 1 peserta.',
        ];
    }
}
