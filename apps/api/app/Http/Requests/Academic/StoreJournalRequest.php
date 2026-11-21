<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class StoreJournalRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'uuid', 'exists:schedules,id'],
            'session_date' => ['required', 'date', 'date_format:Y-m-d'],
            'topic' => ['required', 'string', 'min:2', 'max:255'],
            'method' => ['required', 'string', 'min:2', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'schedule_id.required' => 'Jadwal pelajaran wajib dipilih',
            'schedule_id.exists' => 'Jadwal pelajaran tidak ditemukan',
            'session_date.required' => 'Tanggal pertemuan sesi wajib diisi',
            'session_date.date_format' => 'Format tanggal sesi harus YYYY-MM-DD',
            'topic.required' => 'Materi/topik pembelajaran wajib diisi',
            'topic.min' => 'Materi/topik minimal 2 karakter',
            'method.required' => 'Metode pembelajaran wajib diisi',
            'method.min' => 'Metode pembelajaran minimal 2 karakter',
        ];
    }
}
