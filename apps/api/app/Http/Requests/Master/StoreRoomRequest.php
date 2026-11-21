<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;
use App\Models\Room;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:rooms,code'],
            'name' => ['required', 'string', 'max:150'],
            'room_type' => ['required', 'string', Rule::in(Room::ROOM_TYPES)],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode ruangan wajib diisi',
            'code.unique' => 'Kode ruangan sudah digunakan',
            'name.required' => 'Nama ruangan wajib diisi',
            'room_type.required' => 'Tipe ruangan wajib diisi',
            'room_type.in' => 'Tipe ruangan tidak valid',
            'capacity.required' => 'Kapasitas ruangan wajib diisi',
            'capacity.integer' => 'Kapasitas ruangan harus berupa angka',
            'capacity.min' => 'Kapasitas ruangan minimal 1 orang',
        ];
    }
}
