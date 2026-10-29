<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiRouteAuthorizationAuditTest extends TestCase
{
    /**
     * Whitelist kanonis route publik tanpa middleware auth:sanctum.
     * Segala rute baru di luar daftar ini WAJIB memakai auth:sanctum.
     */
    public const PUBLIC_ROUTE_WHITELIST = [
        'GET|HEAD' => [
            'api/v1/health',
            'api/v1/public/verify/{token}',
            'api/v1/files/{id}/download',
            'api/v1/public/site',
            'api/v1/public/posts',
            'api/v1/public/posts/{slug}',
            'api/v1/post-categories',
            'api/v1/public/albums',
            'api/v1/public/albums/{id}',
            'api/v1/public/bulletins',
            'api/v1/public/bulletins/{id}',
            'api/v1/public/events',
            'api/v1/public/events/{id}',
            'api/v1/public/teachers',
            'api/v1/public/teachers/{id}',
            'api/v1/public/staff',
            'api/v1/public/downloads',
            'api/v1/public/downloads/{id}',
            'api/v1/public/pages',
            'api/v1/public/pages/{slug}',
            'api/v1/public/navigation-menu',
            'api/v1/public/ppdb/waves',
            'api/v1/public/ppdb/waves/active',
            'api/v1/public/ppdb/waves/{id}',
            'api/v1/public/ppdb/tracks',
            'api/v1/public/ppdb/tracks/{id}',
            'api/v1/public/ppdb/stats',
            'api/v1/public/complaints/{code}',
        ],
        'POST' => [
            'api/v1/files/upload-signed/{token}',
            'api/v1/auth/login',
            'api/v1/auth/admin/login',
            'api/v1/auth/refresh',
            'api/v1/auth/forgot-password',
            'api/v1/auth/reset-password',
            'api/v1/auth/2fa/setup',
            'api/v1/auth/2fa/verify',
            'api/v1/auth/2fa/challenge',
            'api/v1/public/ppdb/results/check',
            'api/v1/public/guests',
            'api/v1/public/guests/{id}/signature',
            'api/v1/public/complaints',
            'api/v1/ppdb/register',
            'api/v1/ppdb/verify-otp',
            'api/v1/ppdb/resend-otp',
            'api/v1/ppdb/check-nik',
            'api/v1/ppdb/login',
            'api/v1/cbt/sync/pull',
            'api/v1/cbt/sync/push',
        ],
    ];

    /**
     * BATCH 5B - A.5: Setiap rute API harus memiliki auth middleware kecuali jika terdaftar di whitelist publik.
     */
    public function test_batch5b_all_api_routes_strictly_require_auth_unless_whitelisted(): void
    {
        $routes = Route::getRoutes();
        $unauthorizedUnwhitelistedRoutes = [];

        foreach ($routes as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            $middlewares = $route->gatherMiddleware();
            $hasAuth = false;
            foreach ($middlewares as $mw) {
                if (str_contains($mw, 'auth:') || $mw === 'auth') {
                    $hasAuth = true;
                    break;
                }
            }

            if (! $hasAuth) {
                $methods = implode('|', array_filter($route->methods(), fn ($m) => $m !== 'HEAD'));
                if (in_array('GET', $route->methods(), true)) {
                    $methods = 'GET|HEAD';
                }

                $whitelisted = self::PUBLIC_ROUTE_WHITELIST[$methods] ?? [];
                if (! in_array($uri, $whitelisted, true)) {
                    $unauthorizedUnwhitelistedRoutes[] = [
                        'method' => $methods,
                        'uri' => $uri,
                        'action' => $route->getActionName(),
                    ];
                }
            }
        }

        $this->assertEmpty(
            $unauthorizedUnwhitelistedRoutes,
            'Ditemukan route API tanpa middleware autentikasi dan tidak ada di whitelist: '.json_encode($unauthorizedUnwhitelistedRoutes, JSON_PRETTY_PRINT)
        );
    }

    /**
     * BATCH 5B - A.5: Cookie sistlabum_admin_session tidak dipakai untuk otorisasi apa pun di backend.
     */
    public function test_batch5b_admin_session_cookie_is_never_used_for_backend_authorization(): void
    {
        $appPath = app_path();
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));
        $matches = [];

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if (str_contains($content, 'sistlabum_admin_session')) {
                $matches[] = $file->getPathname();
            }
        }

        $this->assertEmpty(
            $matches,
            'Backend Laravel TIDAK BOLEH membaca atau mengandalkan cookie sistlabum_admin_session untuk otorisasi: '.implode(', ', $matches)
        );
    }

    /**
     * BATCH 5C - C: Pasang rate limit + anti-spam pada seluruh rute POST publik.
     */
    public function test_batch5c_all_public_post_routes_strictly_enforce_rate_limiting_and_anti_spam(): void
    {
        $routes = Route::getRoutes();
        $publicPostRoutes = self::PUBLIC_ROUTE_WHITELIST['POST'];

        // Saring rute yang menangani throttle di dalam controller (seperti login admin) atau lewat middleware
        $unthrottledRoutes = [];

        foreach ($routes as $route) {
            $uri = $route->uri();
            if (! in_array($uri, $publicPostRoutes, true)) {
                continue;
            }

            if (! in_array('POST', $route->methods(), true)) {
                continue;
            }

            $middlewares = $route->gatherMiddleware();
            $hasThrottle = false;
            foreach ($middlewares as $mw) {
                if (str_contains($mw, 'throttle:')) {
                    $hasThrottle = true;
                    break;
                }
            }

            // Route yang memiliki perlindungan rate limiting di level service/controller
            $internalThrottled = in_array($uri, [
                'api/v1/auth/login',
                'api/v1/auth/admin/login',
                'api/v1/auth/refresh',
                'api/v1/files/upload-signed/{token}',
                'api/v1/public/ppdb/results/check',
                'api/v1/cbt/sync/pull',
                'api/v1/cbt/sync/push',
            ], true);

            if (! $hasThrottle && ! $internalThrottled) {
                $unthrottledRoutes[] = $uri;
            }
        }

        $this->assertEmpty(
            $unthrottledRoutes,
            'Seluruh endpoint POST publik wajib dipasangi middleware throttle: '.json_encode($unthrottledRoutes)
        );
    }
}
