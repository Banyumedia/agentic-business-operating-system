<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentCompany
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->current_company_id) {
            $company = $user->companies()->first();

            if ($company) {
                $user->current_company_id = $company->id;
                $user->save();
            }
        }

        return $next($request);
    }
}
