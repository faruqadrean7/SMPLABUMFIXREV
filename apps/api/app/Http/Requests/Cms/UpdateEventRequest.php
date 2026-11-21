<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;
use App\Models\SchoolEvent;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'category' => ['sometimes', 'required', 'string', Rule::in(SchoolEvent::CATEGORIES)],
            'visibility' => ['sometimes', 'required', 'string', Rule::in(SchoolEvent::VISIBILITIES)],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['sometimes', 'required', 'date'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $startAt = $this->input('start_at');
            $endAt = $this->input('end_at');

            if ($startAt && $endAt && strtotime($endAt) < strtotime($startAt)) {
                $validator->errors()->add('end_at', 'Waktu selesai tidak boleh lebih awal dari waktu mulai');
            }
        });
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
