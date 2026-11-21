<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class CheckInStaffAttendanceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'photo_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
            'photo_file_id' => ['nullable', 'uuid', 'exists:files,id'],
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
            'photo_file.mimes' => 'Foto presensi/selfie harus berformat JPG, JPEG, atau PNG',
            'photo_file.max' => 'Ukuran foto presensi/selfie maksimal 5 MB',
            'photo_file_id.exists' => 'Berkas foto presensi tidak ditemukan di sistem',
            'notes.max' => 'Catatan presensi maksimal 500 karakter',
        ];
    }
}
