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

        // Check if user has any of the permissions, matches allowed role codes, or is super_admin
        $hasPermission = $user->roles()
            ->where(function ($query) use ($permissions) {
                $query->whereIn('code', $permissions)
                    ->orWhereHas('permissions', function ($pq) use ($permissions) {
                        $pq->whereIn('code', $permissions);
                    });
            })
            ->exists() || $user->roles()->where('code', 'super_admin')->exists();

        if (! $hasPermission) {
            return $this->errorResponse('Anda tidak memiliki izin untuk mengakses resource ini', 'FORBIDDEN', 403);
        }

        return $next($request);
    }
}
