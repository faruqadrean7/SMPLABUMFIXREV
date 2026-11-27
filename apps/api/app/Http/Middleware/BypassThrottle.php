<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

class BypassThrottle
{
    /**
     * Handle an incoming request when rate limiting is disabled strictly for automated testing.
     * P1-05: Di luar environment 'testing', delegasikan paksa ke ThrottleRequests standar.
     */
    public function handle(Request $request, Closure $next, ...$args)
    {
        if (! app()->environment('testing')) {
            return app(ThrottleRequests::class)->handle($request, $next, ...$args);
        }

        return $next($request);
    }
}
