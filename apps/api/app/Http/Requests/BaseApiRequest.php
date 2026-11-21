<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

abstract class BaseApiRequest extends FormRequest
{
    use ApiResponse;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt according to SISTLABUM API standard:
     * 400 Bad Request / VALIDATION_FAILED
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
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Data tidak valid',
                    'details' => $details,
                    'requestId' => $requestId,
                ],
                'message' => 'Data tidak valid',
                'errors' => $errors,
            ], 400, ['X-Request-Id' => $requestId])
        );
    }
}
