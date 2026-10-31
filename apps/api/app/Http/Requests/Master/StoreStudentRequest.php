<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseApiRequest;

class StoreStudentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'nis' => ['required', 'string', 'max:30', 'unique:students,nis'],
            'nisn' => ['required', 'string', 'max:30', 'unique:students,nisn'],
            'nik' => ['required', 'string', 'regex:/^[0-9]{16}$/'],
            'full_name' => ['required', 'string', 'max:200'],
            'gender' => ['required', 'in:L,P'],
            'birth_place' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'photo_file_id' => ['nullable', 'string', 'exists:files,id'],
            'entry_date' => ['required', 'date'],
            'status' => ['nullable', 'in:aktif,lulus,pindah,keluar'],
            'program_type' => ['nullable', 'in:reguler,tahfidz,icp,inklusi'],
        ];
    }

    public function messages(): array
    {
        return [
            'nis.required' => 'NIS wajib diisi',
            'nis.unique' => 'NIS sudah terdaftar di sistem',
            'nisn.required' => 'NISN wajib diisi',
            'nisn.unique' => 'NISN sudah terdaftar di sistem',
            'nik.required' => 'NIK wajib diisi',
            'nik.regex' => 'NIK harus berupa 16 digit angka',
            'full_name.required' => 'Nama lengkap siswa wajib diisi',
            'gender.required' => 'Jenis kelamin wajib dipilih (L / P)',
            'gender.in' => 'Pilihan jenis kelamin harus L (Laki-laki) atau P (Perempuan)',
            'birth_place.required' => 'Tempat lahir wajib diisi',
            'birth_date.required' => 'Tanggal lahir wajib diisi',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini',
            'entry_date.required' => 'Tanggal masuk wajib diisi',
            'status.in' => 'Status siswa harus salah satu dari: aktif, lulus, pindah, keluar',
        ];
    }
}
