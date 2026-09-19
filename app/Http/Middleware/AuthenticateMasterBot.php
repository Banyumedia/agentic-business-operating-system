<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateMasterBot
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-Master-Bot-Key');
        // Fail-closed: secret kosong/ter-set dari config, tanpa default yang
        // bisa ditebak. Respon tidak pernah membocorkan apakah key ada.
        $secret = (string) config('services.master_bot.secret');

        if ($secret === '' || ! hash_equals($secret, (string) ($key ?? ''))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
