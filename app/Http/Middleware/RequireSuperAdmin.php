<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->is_platform_admin) {
            abort(403, 'Akses ditolak. Anda bukan super admin.');
        }

        return $next($request);
    }
}
