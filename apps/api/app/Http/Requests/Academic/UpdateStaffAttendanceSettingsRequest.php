<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class UpdateStaffAttendanceSettingsRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:50000'],
            'late_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'Koordinat latitude sekolah wajib diisi',
            'latitude.numeric' => 'Latitude harus berupa angka',
            'latitude.between' => 'Latitude harus bernilai antara -90 dan 90',
            'longitude.required' => 'Koordinat longitude sekolah wajib diisi',
            'longitude.numeric' => 'Longitude harus berupa angka',
            'longitude.between' => 'Longitude harus bernilai antara -180 dan 180',
            'radius_meters.required' => 'Radius toleransi GPS wajib diisi',
            'radius_meters.min' => 'Radius minimal adalah 10 meter',
            'radius_meters.max' => 'Radius maksimal adalah 50.000 meter (50 km)',
            'late_time.required' => 'Jam batas keterlambatan wajib diisi',
            'late_time.regex' => 'Format jam batas keterlambatan harus HH:mm atau HH:mm:ss',
        ];
    }
}
