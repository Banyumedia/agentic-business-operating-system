<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminImpersonationSession;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminImpersonationController extends Controller
{
    public function impersonate(Request $request, Company $company)
    {
        $sessionId = Str::uuid()->toString();

        AdminImpersonationSession::create([
            'admin_user_id' => $request->user()->id,
            'target_company_id' => $company->id,
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            // F2 (QA MQ-01): TTL terbatas - sesi impersonasi kedaluwarsa
            // otomatis meski admin lupa menekan "Akhiri Sesi".
            'expires_at' => now()->addHours(2),
        ]);

        $request->session()->put('admin_impersonation_id', $sessionId);
        $request->session()->put('active_company', $company->id);

        $request->user()->update(['current_company_id' => $company->id]);
        $request->user()->current_company_id = $company->id;

        return redirect()->route('app.dashboard');
    }

    public function stop(Request $request)
    {
        $sessionId = $request->session()->get('admin_impersonation_id');
        if ($sessionId) {
            AdminImpersonationSession::where('session_id', $sessionId)->delete();
            $request->session()->forget('admin_impersonation_id');
        }

        $request->session()->forget('active_company');
        $request->user()->update(['current_company_id' => null]);

        return redirect()->route('admin.dashboard');
    }
}
