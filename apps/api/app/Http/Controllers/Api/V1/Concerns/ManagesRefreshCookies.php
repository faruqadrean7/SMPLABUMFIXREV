<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * P1-01: Dual-mode refresh token transport.
 *
 * - Klien Web (mengirim header `X-Auth-Token-Transport: cookie`):
 *   refresh token hanya dikirim melalui cookie HttpOnly/SameSite=Lax dan TIDAK
 *   disertakan di body JSON sehingga tidak pernah tersentuh JS / localStorage.
 * - Klien Mobile Android/iOS & klien kompatibel lama (tanpa header tersebut):
 *   refresh token tetap dikembalikan di body JSON `data.tokens.refreshToken`
 *   sambil tetap melampirkan cookie HttpOnly secara aman.
 */
trait ManagesRefreshCookies
{
    protected const REFRESH_COOKIE_NAME = 'sistlabum_refresh_token';

    protected const REFRESH_COOKIE_PATH = '/api/v1/auth';

    protected const REFRESH_COOKIE_TTL_MINUTES = 20160; // 14 hari

    protected function isCookieOnlyTransport(Request $request): bool
    {
        return strtolower(trim((string) $request->header('X-Auth-Token-Transport', ''))) === 'cookie';
    }

    protected function resolveRefreshTokenFromRequest(Request $request): ?string
    {
        $fromCookie = $request->cookie(self::REFRESH_COOKIE_NAME)
            ?? $request->cookie('refreshToken');

        if (is_string($fromCookie) && $fromCookie !== '') {
            return $fromCookie;
        }

        $fromBody = $request->input('refreshToken');

        return is_string($fromBody) && $fromBody !== '' ? $fromBody : null;
    }

    /**
     * Siapkan payload auth untuk klien sambil mengekstrak plain refresh token untuk cookie HttpOnly.
     *
     * @param  array<string, mixed>  $authData
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    protected function prepareAuthPayloadForTransport(array $authData, Request $request): array
    {
        $plainRefreshToken = $authData['tokens']['refreshToken'] ?? null;

        if ($this->isCookieOnlyTransport($request) && isset($authData['tokens']['refreshToken'])) {
            unset($authData['tokens']['refreshToken']);
        }

        return [$authData, is_string($plainRefreshToken) ? $plainRefreshToken : null];
    }

    protected function attachRefreshCookie(JsonResponse $response, ?string $plainRefreshToken, Request $request): JsonResponse
    {
        if (! $plainRefreshToken) {
            return $response;
        }

        $secure = $request->isSecure() || app()->environment('production');

        $cookie = Cookie::create(
            name: self::REFRESH_COOKIE_NAME,
            value: $plainRefreshToken,
            expire: now()->addMinutes(self::REFRESH_COOKIE_TTL_MINUTES),
            path: self::REFRESH_COOKIE_PATH,
            domain: null,
            secure: $secure,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX
        );

        return $response->withCookie($cookie);
    }

    protected function clearRefreshCookie(JsonResponse $response, Request $request): JsonResponse
    {
        $secure = $request->isSecure() || app()->environment('production');

        $expired = Cookie::create(
            name: self::REFRESH_COOKIE_NAME,
            value: '',
            expire: now()->subMinutes(5),
            path: self::REFRESH_COOKIE_PATH,
            domain: null,
            secure: $secure,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX
        );

        return $response->withCookie($expired);
    }
}
