<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;
use App\Models\Room;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('rooms', 'code')->ignore($id)],
            'name' => ['nullable', 'string', 'max:150'],
            'room_type' => ['nullable', 'string', Rule::in(Room::ROOM_TYPES)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Kode ruangan sudah digunakan',
            'room_type.in' => 'Tipe ruangan tidak valid',
            'capacity.integer' => 'Kapasitas ruangan harus berupa angka',
            'capacity.min' => 'Kapasitas ruangan minimal 1 orang',
        ];
    }
}
