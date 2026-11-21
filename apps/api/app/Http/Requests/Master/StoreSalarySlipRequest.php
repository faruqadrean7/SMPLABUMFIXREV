<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalarySlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'payment_date' => ['nullable', 'date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'net_salary' => ['nullable', 'numeric', 'min:0'],
            'slip_number' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:draft,published,paid'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_month.required' => 'Bulan periode wajib diisi.',
            'period_month.between' => 'Bulan periode harus bernilai antara 1 sampai 12.',
            'period_year.required' => 'Tahun periode wajib diisi.',
            'file.mimes' => 'Berkas slip gaji harus berformat PDF, JPG, atau PNG.',
            'file.max' => 'Ukuran berkas slip gaji tidak boleh melebihi 10 MB.',
        ];
    }
}
