<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The staff context, query results and order list are cached per request (scoped services).
 * PHP-FPM starts every request fresh anyway; this makes the same true for long-running
 * workers (Octane) and for the test client, so a change made in the app is never hidden by
 * a previous request's cache.
 */
class ResetRequestScope
{
    public function handle(Request $request, Closure $next)
    {
        app()->forgetScopedInstances();

        return $next($request);
    }
}
