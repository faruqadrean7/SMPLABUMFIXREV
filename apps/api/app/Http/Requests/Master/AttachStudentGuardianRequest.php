<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class AttachStudentGuardianRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'guardian_id' => ['required', 'string', 'exists:guardians,id'],
            'relationship' => ['required', 'string', 'max:50'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'guardian_id.required' => 'ID orang tua/wali wajib dipilih',
            'guardian_id.exists' => 'Orang tua/wali tidak ditemukan di sistem',
            'relationship.required' => 'Hubungan relasi wajib diisi (misal: ayah, ibu, wali)',
        ];
    }
}
