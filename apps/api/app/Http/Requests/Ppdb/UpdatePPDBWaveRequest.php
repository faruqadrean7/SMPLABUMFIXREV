<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class UpdatePPDBWaveRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'open_at' => ['sometimes', 'required', 'date'],
            'close_at' => ['sometimes', 'required', 'date'],
            'result_publish_at' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', 'string', 'in:draft,aktif,ditutup,selesai'],
            'school_id' => ['nullable', 'uuid'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $openAt = $this->input('open_at');
            $closeAt = $this->input('close_at');
            $publishAt = $this->input('result_publish_at');

            if ($openAt && $closeAt && strtotime($closeAt) <= strtotime($openAt)) {
                $validator->errors()->add('close_at', 'Tanggal tutup pendaftaran harus setelah tanggal buka');
            }

            if ($closeAt && $publishAt && strtotime($publishAt) < strtotime($closeAt)) {
                $validator->errors()->add('result_publish_at', 'Tanggal pengumuman hasil tidak boleh sebelum tanggal tutup pendaftaran');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama gelombang PPDB wajib diisi',
            'open_at.required' => 'Tanggal buka pendaftaran wajib diisi',
            'close_at.required' => 'Tanggal tutup pendaftaran wajib diisi',
            'result_publish_at.required' => 'Tanggal pengumuman hasil wajib diisi',
            'status.in' => 'Status gelombang harus berupa draft, aktif, ditutup, atau selesai',
        ];
    }
}
