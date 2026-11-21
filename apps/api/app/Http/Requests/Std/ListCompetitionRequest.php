<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class ListCompetitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'in:Sekolah,Kecamatan,Kota,Kabupaten,Provinsi,Nasional,Internasional'],
            'category' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:direncanakan,pendaftaran,berlangsung,selesai,dibatalkan'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
