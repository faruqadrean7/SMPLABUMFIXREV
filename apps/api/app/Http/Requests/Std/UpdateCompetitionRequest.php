<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompetitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'organizer' => ['nullable', 'string', 'max:150'],
            'level' => ['sometimes', 'required', 'string', 'in:Sekolah,Kecamatan,Kota,Kabupaten,Provinsi,Nasional,Internasional'],
            'category' => ['nullable', 'string', 'max:50'],
            'held_at' => ['sometimes', 'required', 'date'],
            'registration_deadline' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:direncanakan,pendaftaran,berlangsung,selesai,dibatalkan'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
