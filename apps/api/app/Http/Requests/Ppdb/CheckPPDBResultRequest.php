<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class CheckPPDBResultRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'registration_no' => ['required', 'string', 'max:50'],
            'birth_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'registration_no.required' => 'Nomor pendaftaran wajib diisi',
            'registration_no.max' => 'Nomor pendaftaran maksimal 50 karakter',
            'birth_date.required' => 'Tanggal lahir wajib diisi',
            'birth_date.date_format' => 'Format tanggal lahir harus berupa YYYY-MM-DD (contoh: 2010-05-15)',
        ];
    }
}
