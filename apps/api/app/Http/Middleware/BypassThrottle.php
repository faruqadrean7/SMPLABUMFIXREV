<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BypassThrottle
{
    /**
     * Handle an incoming request when rate limiting is disabled for testing.
     */
    public function handle(Request $request, Closure $next, ...$args)
    {
        return $next($request);
    }
}
