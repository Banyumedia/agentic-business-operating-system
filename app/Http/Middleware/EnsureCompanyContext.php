<?php

namespace App\Http\Middleware;

use App\Contracts\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            app(CompanyContext::class)->current();
        } catch (InvalidArgumentException) {
            abort(404);
        } catch (LogicException $e) {
            abort(403, 'Context Error: '.$e->getMessage());
        }

        return $next($request);
    }
}
