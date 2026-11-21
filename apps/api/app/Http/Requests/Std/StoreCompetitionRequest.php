<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompetitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:200'],
            'organizer' => ['nullable', 'string', 'max:150'],
            'level' => ['required', 'string', 'in:Sekolah,Kecamatan,Kota,Kabupaten,Provinsi,Nasional,Internasional'],
            'category' => ['nullable', 'string', 'max:50'],
            'held_at' => ['required', 'date'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:held_at'],
            'status' => ['nullable', 'string', 'in:direncanakan,pendaftaran,berlangsung,selesai,dibatalkan'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
