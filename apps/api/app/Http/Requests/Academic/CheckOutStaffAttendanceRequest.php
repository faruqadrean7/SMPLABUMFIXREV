<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class CheckOutStaffAttendanceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'lat.numeric' => 'Koordinat latitude harus berupa angka desimal',
            'lat.between' => 'Koordinat latitude tidak valid',
            'lng.numeric' => 'Koordinat longitude harus berupa angka desimal',
            'lng.between' => 'Koordinat longitude tidak valid',
            'notes.max' => 'Catatan presensi maksimal 500 karakter',
        ];
    }
}
