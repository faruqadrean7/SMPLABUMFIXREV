<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TwoFactorAuthController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactorService
    ) {}

    /**
     * P1-06: Seluruh endpoint 2FA/TOTP dinonaktifkan (410 Gone) sesuai kebijakan sistem (AGENTS.md §6.11).
     */
    public function disabled(Request $request): JsonResponse
    {
        $requestId = $request->header('X-Request-Id', (string) Str::uuid());

        return response()->json([
            'error' => [
                'code' => 'FEATURE_DISABLED',
                'message' => 'Fitur autentikasi dua faktor (2FA/TOTP) dinonaktifkan sesuai kebijakan sistem. Silakan masuk langsung menggunakan kredensial akun Anda.',
                'details' => [],
                'requestId' => $requestId,
            ],
        ], 410, ['X-Request-Id' => $requestId]);
    }

    public function setup(Request $request): JsonResponse
    {
        return $this->disabled($request);
    }

    public function verify(Request $request): JsonResponse
    {
        return $this->disabled($request);
    }

    public function disable(Request $request): JsonResponse
    {
        return $this->disabled($request);
    }
}
