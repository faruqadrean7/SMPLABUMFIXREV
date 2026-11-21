<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;
use App\Models\Grade;
use App\Services\GradeService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StoreGradeRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $componentId = $this->input('component_id');
        if ($componentId) {
            try {
                app(GradeService::class)->checkGradeEntryAuthorization($user, $componentId);
            } catch (HttpException $e) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'component_id' => ['required', 'uuid', 'exists:grade_components,id'],
            'period_id' => ['required', 'uuid', 'exists:grade_periods,id'],
            'value' => ['required', 'numeric', 'between:0,100'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $studentId = $this->input('student_id');
            $componentId = $this->input('component_id');
            $periodId = $this->input('period_id');
            $newValue = $this->input('value');
            $reason = $this->input('reason');

            if ($studentId && $componentId && $periodId && $newValue !== null) {
                $existing = Grade::where('student_id', $studentId)
                    ->where('component_id', $componentId)
                    ->where('period_id', $periodId)
                    ->first();

                if ($existing && (float) $existing->value !== (float) $newValue && empty(trim((string) $reason))) {
                    $validator->errors()->add('reason', 'Alasan perubahan nilai wajib diisi saat mengubah nilai yang sudah ada.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Siswa wajib dipilih',
            'student_id.exists' => 'Siswa tidak ditemukan di sistem',
            'component_id.required' => 'Komponen nilai wajib dipilih',
            'component_id.exists' => 'Komponen nilai tidak ditemukan di sistem',
            'period_id.required' => 'Periode penilaian wajib dipilih',
            'period_id.exists' => 'Periode penilaian tidak ditemukan di sistem',
            'value.required' => 'Nilai wajib diisi',
            'value.numeric' => 'Nilai harus berupa angka',
            'value.between' => 'Nilai harus berada dalam rentang 0 sampai 100',
        ];
    }
}
