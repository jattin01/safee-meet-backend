<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    // public function handle(Request $request, Closure $next): Response
    // {
    //     return $next($request);
    // }

    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            abort(401, 'Unauthorized');
        }

        // Load role if not already loaded
        $admin->loadMissing('role');

        if (! $admin->role) {
            abort(403, 'No role assigned.');
        }

        if (! in_array($admin->role->slug, $roles)) {
            abort(403, 'You are not authorized.');
        }

        return $next($request);
    }
}
