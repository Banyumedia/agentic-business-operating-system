<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateMasterBot
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-Master-Bot-Key');
        $secret = env('MASTER_BOT_SECRET', 'fake-master-secret');

        if (! hash_equals($secret, $key ?? '')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
