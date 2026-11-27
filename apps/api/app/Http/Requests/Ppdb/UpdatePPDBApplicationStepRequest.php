<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class UpdatePPDBApplicationStepRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            // Step 1: Biodata Calon Siswa (P0-21: sinkron penuh dengan FE ppdb/dashboard)
            'full_name' => ['nullable', 'string', 'max:200'],
            'nik' => ['nullable', 'string', 'regex:/^[0-9]{16}$/'],
            'nisn' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:L,P'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],

            // Step 2: Data Orang Tua / Wali (format flat FE & format array parents kompatibel mundur)
            'father_name' => ['nullable', 'string', 'max:200'],
            'father_job' => ['nullable', 'string', 'max:100'],
            'father_phone' => ['nullable', 'string', 'max:30'],
            'mother_name' => ['nullable', 'string', 'max:200'],
            'mother_job' => ['nullable', 'string', 'max:100'],
            'mother_phone' => ['nullable', 'string', 'max:30'],
            'income_range' => ['nullable', 'string', 'max:100'],
            'parents' => ['nullable', 'array'],
            'parents.*.parent_type' => ['required_with:parents', 'string', 'in:ayah,ibu,wali'],
            'parents.*.name' => ['nullable', 'string', 'max:200'],
            'parents.*.occupation' => ['nullable', 'string', 'max:100'],
            'parents.*.income_range' => ['nullable', 'string', 'max:100'],
            'parents.*.phone' => ['nullable', 'string', 'max:30'],

            // Step 3: Asal Sekolah & Nilai Rapor (format flat FE & nested school.* kompatibel mundur)
            'origin_school' => ['nullable', 'string', 'max:200'],
            'npsn' => ['nullable', 'string', 'max:20'],
            'graduation_year' => ['nullable', 'string', 'max:10'],
            'average_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rapor_details' => ['nullable', 'array'],
            'rapor_details.indonesian' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rapor_details.math' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rapor_details.science' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rapor_details.english' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'school' => ['nullable', 'array'],
            'school.name' => ['nullable', 'string', 'max:200'],
            'school.npsn' => ['nullable', 'string', 'max:20'],
            'school.address' => ['nullable', 'string'],

            // Step 4: Pilihan Jalur & Gelombang
            'wave_id' => ['nullable', 'uuid', 'exists:ppdb_waves,id'],
            'track_id' => ['nullable', 'uuid', 'exists:ppdb_tracks,id'],
            'major_choice' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'data' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.regex' => 'NIK harus terdiri dari tepat 16 digit angka',
            'gender.in' => 'Jenis kelamin harus L (Laki-laki) atau P (Perempuan)',
            'parents.*.parent_type.in' => 'Tipe orang tua harus salah satu dari: ayah, ibu, atau wali',
            'average_score.numeric' => 'Rata-rata nilai rapor harus berupa angka (0-100)',
            'track_id.exists' => 'Jalur pendaftaran yang dipilih tidak valid',
            'wave_id.exists' => 'Gelombang pendaftaran yang dipilih tidak valid',
        ];
    }
}
