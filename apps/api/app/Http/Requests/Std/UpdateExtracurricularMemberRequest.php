<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExtracurricularMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', 'string', 'in:aktif,nonaktif,keluar'],
            'role' => ['sometimes', 'required', 'string', 'in:ketua,wakil_ketua,sekretaris,bendahara,anggota'],
            'final_grade' => ['nullable', 'string', 'in:A,B,C,D,Sangat Baik,Baik,Cukup,Kurang'],
        ];
    }
}
