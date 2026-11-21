<?php

namespace App\Http\Requests\Std;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTripParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('id') && ! $this->has('trip_id')) {
            $this->merge(['trip_id' => $this->route('id')]);
        }
    }

    public function rules(): array
    {
        return [
            'trip_id' => ['required', 'uuid', 'exists:trips,id'],
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
