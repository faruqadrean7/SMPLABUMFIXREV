<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class UpdateTimeSlotRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'uuid'],
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'start_time' => ['sometimes', 'required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'end_time' => ['sometimes', 'required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'day_type' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama slot jam pelajaran wajib diisi',
            'name.max' => 'Nama slot jam maksimal 50 karakter',
            'start_time.required' => 'Waktu mulai wajib diisi',
            'start_time.regex' => 'Format waktu mulai harus HH:mm (contoh: 07:00)',
            'end_time.required' => 'Waktu selesai wajib diisi',
            'end_time.regex' => 'Format waktu selesai harus HH:mm (contoh: 07:45)',
        ];
    }
}
