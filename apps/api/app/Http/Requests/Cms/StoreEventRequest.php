<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;
use App\Models\SchoolEvent;
use Illuminate\Validation\Rule;

class StoreEventRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', Rule::in(SchoolEvent::CATEGORIES)],
            'visibility' => ['required', 'string', Rule::in(SchoolEvent::VISIBILITIES)],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul agenda wajib diisi',
            'category.required' => 'Kategori agenda wajib diisi',
            'category.in' => 'Kategori agenda harus berupa ujian, libur, atau event',
            'visibility.required' => 'Visibilitas agenda wajib diisi',
            'visibility.in' => 'Visibilitas agenda harus berupa publik atau internal',
            'start_at.required' => 'Waktu mulai agenda wajib diisi',
            'end_at.required' => 'Waktu selesai agenda wajib diisi',
            'end_at.after_or_equal' => 'Waktu selesai tidak boleh lebih awal dari waktu mulai',
        ];
    }
}
