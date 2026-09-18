<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\HermesProfile;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenantBot
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $profile = HermesProfile::where('webhook_secret_reference', $token)->first();
        if (! $profile) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $waNumber = $request->header('X-Caller-Wa-Number');
        $companyId = $request->input('company_id');

        if (! $waNumber) {
            return response()->json(['error' => 'Missing X-Caller-Wa-Number header'], 403);
        }

        if (! $companyId) {
            return response()->json(['error' => 'company_id is required'], 400);
        }

        $user = User::where('wa_number', $waNumber)->first();
        if (! $user) {
            return response()->json(['error' => 'User not found'], 403);
        }

        $profileHasAccess = $profile->companies()->where('companies.id', $companyId)->exists();
        if (! $profileHasAccess) {
            return response()->json(['error' => 'Profile does not have access to this company'], 403);
        }

        $company = Company::find($companyId);
        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $userHasAccess = $company->owner_user_id === $user->id;

        if (! $userHasAccess) {
            return response()->json(['error' => 'User does not belong to this company'], 403);
        }

        if (! $company->feature('system.ai_agent')) {
            return response()->json(['error' => 'AI Agent capability is disabled for this company'], 403);
        }

        $request->attributes->set('bot_profile', $profile);
        $request->attributes->set('bot_caller_user', $user);

        return $next($request);
    }
}
