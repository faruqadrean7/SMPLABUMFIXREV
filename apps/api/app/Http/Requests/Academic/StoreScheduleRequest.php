<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class StoreScheduleRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'class_id' => ['required', 'uuid', 'exists:classes,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
            'time_slot_id' => ['required', 'uuid', 'exists:time_slots,id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
        ];
    }

    public function messages(): array
    {
        return [
            'semester_id.required' => 'Semester wajib dipilih',
            'semester_id.exists' => 'Semester tidak ditemukan',
            'class_id.required' => 'Kelas/rombel wajib dipilih',
            'class_id.exists' => 'Kelas/rombel tidak ditemukan',
            'subject_id.required' => 'Mata pelajaran wajib dipilih',
            'subject_id.exists' => 'Mata pelajaran tidak ditemukan',
            'teacher_id.required' => 'Guru pengajar wajib dipilih',
            'teacher_id.exists' => 'Guru tidak ditemukan',
            'room_id.exists' => 'Ruangan tidak ditemukan',
            'time_slot_id.required' => 'Slot jam pelajaran wajib dipilih',
            'time_slot_id.exists' => 'Slot jam pelajaran tidak ditemukan',
            'day_of_week.required' => 'Hari dalam sepekan wajib dipilih (1=Senin s.d. 7=Minggu)',
            'day_of_week.between' => 'Hari harus bernilai antara 1 (Senin) hingga 7 (Minggu)',
        ];
    }
}
