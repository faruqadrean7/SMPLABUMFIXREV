<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'nis' => ['nullable', 'string', 'max:30', Rule::unique('students', 'nis')->ignore($id)],
            'nisn' => ['nullable', 'string', 'max:30', Rule::unique('students', 'nisn')->ignore($id)],
            'nik' => ['nullable', 'string', 'regex:/^[0-9]{16}$/'],
            'full_name' => ['nullable', 'string', 'max:200'],
            'gender' => ['nullable', 'in:L,P'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'photo_file_id' => ['nullable', 'string', 'exists:files,id'],
            'entry_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:aktif,lulus,pindah,keluar'],
            'status_reason' => ['nullable', 'string'],
            'status_effective_date' => ['nullable', 'date'],
            'program_type' => ['nullable', 'in:reguler,tahfidz,icp,inklusi'],
        ];
    }

    public function messages(): array
    {
        return [
            'nis.unique' => 'NIS sudah terdaftar di sistem',
            'nisn.unique' => 'NISN sudah terdaftar di sistem',
            'nik.regex' => 'NIK harus berupa 16 digit angka',
            'gender.in' => 'Pilihan jenis kelamin harus L (Laki-laki) atau P (Perempuan)',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini',
            'status.in' => 'Status siswa harus salah satu dari: aktif, lulus, pindah, keluar',
        ];
    }
}
