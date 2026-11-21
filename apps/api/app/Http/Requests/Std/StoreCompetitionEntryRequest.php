<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompetitionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('id') && ! $this->has('competition_id')) {
            $this->merge(['competition_id' => $this->route('id')]);
        }
    }

    public function rules(): array
    {
        return [
            'competition_id' => ['required', 'uuid', 'exists:competitions,id'],
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'coach_teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'status' => ['nullable', 'string', 'in:terdaftar,dikonfirmasi,hadir,gugur,selesai,dibatalkan'],
            'result' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
