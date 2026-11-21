<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class UpdateJournalRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'session_date' => ['sometimes', 'required', 'date', 'date_format:Y-m-d'],
            'topic' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'method' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'session_date.required' => 'Tanggal pertemuan sesi wajib diisi',
            'session_date.date_format' => 'Format tanggal sesi harus YYYY-MM-DD',
            'topic.required' => 'Materi/topik pembelajaran wajib diisi',
            'topic.min' => 'Materi/topik minimal 2 karakter',
            'method.required' => 'Metode pembelajaran wajib diisi',
            'method.min' => 'Metode pembelajaran minimal 2 karakter',
        ];
    }
}
