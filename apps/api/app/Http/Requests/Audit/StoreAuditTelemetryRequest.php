<?php

namespace App\Http\Requests\Audit;

use App\Http\Requests\BaseApiRequest;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class StoreAuditTelemetryRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail) {
                    $val = strtolower(trim((string) $value));
                    if (! str_starts_with($val, 'ui.') && ! str_starts_with($val, 'telemetry.')) {
                        $fail('Aksi telemetri harus berawalan "ui." atau "telemetry.".');

                        return;
                    }

                    $sensitivePatterns = [
                        '/^(academic\.)?grade\.(update|entered|bulk_entered|store|create|delete)/',
                        '/^(academic\.)?report_card\.(lock|unlock|approve|bulk_lock|bulk_approve)/',
                        '/^(progress_report)\.(lock|unlock|approve|bulk_approve)/',
                        '/^(auth\.)?role\.(assign|attach|sync)/',
                        '/^user\.roles_assigned/',
                        '/^(report|data)\.export/',
                        '/\b(approve|reject|unlock)\b/',
                    ];

                    foreach ($sensitivePatterns as $pattern) {
                        if (preg_match($pattern, $val)) {
                            $fail('Aksi bisnis sensitif tidak diizinkan dikirim langsung ke audit log dari klien. Audit ini dicatat secara otomatis dan otoritatif oleh server pada endpoint terkait.');

                            return;
                        }
                    }
                },
            ],
            'entity' => [
                'nullable',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! $value) {
                        return;
                    }
                    $normalized = strtolower(str_replace(['_', '-'], '', (string) $value));
                    $prohibited = ['grade', 'reportcard', 'progressreport', 'userrole', 'role', 'auditlog'];
                    if (in_array($normalized, $prohibited, true)) {
                        $fail('Entitas bisnis sensitif tidak diizinkan pada telemetri audit klien.');
                    }
                },
            ],
            'entity_id' => ['nullable', 'string', 'max:100'],
            'old_values' => ['nullable', 'array'],
            'new_values' => ['nullable', 'array'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Handle failed validation: return 422 with UNPROCESSABLE_ENTITY for contract compatibility
     */
    protected function failedValidation(Validator $validator): void
    {
        $details = [];
        $errors = $validator->errors()->toArray();
        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $details[] = [
                    'field' => $field,
                    'message' => $message,
                ];
            }
        }

        $requestId = request()->header('X-Request-Id', (string) Str::uuid());

        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code' => 'UNPROCESSABLE_ENTITY',
                    'message' => 'Data telemetri audit tidak valid atau memuat aksi sensitif terlarang.',
                    'details' => $details,
                    'requestId' => $requestId,
                ],
                'message' => 'Data telemetri audit tidak valid atau memuat aksi sensitif terlarang.',
            ], 422)
        );
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Aksi telemetri wajib diisi.',
            'action.string' => 'Aksi telemetri harus berupa teks.',
            'action.max' => 'Aksi telemetri maksimal 100 karakter.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }
}
