<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Sesi tidak valid atau telah berakhir', 'UNAUTHENTICATED', 401);
        }

        // Cache role codes & permission codes per user (TTL 5 menit untuk optimasi performa N+1)
        $cacheKey = "user_perm_cache:{$user->id}";
        $cachedAuth = cache()->remember($cacheKey, 300, function () use ($user) {
            $user->loadMissing('roles.permissions');
            $roleCodes = $user->roles->pluck('code')->all();
            $permCodes = $user->roles->flatMap->permissions->pluck('code')->unique()->all();

            return [
                'is_super_admin' => in_array('super_admin', $roleCodes, true),
                'role_codes' => $roleCodes,
                'permission_codes' => $permCodes,
            ];
        });

        if ($cachedAuth['is_super_admin']) {
            return $next($request);
        }

        $hasPermission = false;
        foreach ($permissions as $permission) {
            if (in_array($permission, $cachedAuth['role_codes'], true) || in_array($permission, $cachedAuth['permission_codes'], true)) {
                $hasPermission = true;
                break;
            }
        }

        if (! $hasPermission) {
            return $this->errorResponse('Anda tidak memiliki izin untuk mengakses resource ini', 'FORBIDDEN', 403);
        }

        return $next($request);
    }
}
