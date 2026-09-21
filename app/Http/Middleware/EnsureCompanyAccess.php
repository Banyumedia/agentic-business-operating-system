<?php

namespace App\Http\Middleware;

use App\Models\AdminImpersonationSession;
use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $companyId = $user->current_company_id;

        // Sumber kebenaran ganda: kolom `users.current_company_id` (Eloquent)
        // atau session `active_company` (driver JSON / test). Hormati keduanya.
        if (! $companyId && session()->has('active_company')) {
            $companyId = session('active_company');
        }

        if (! $companyId) {
            // User belum punya/memilih company (mis. baru daftar, belum
            // onboarding). Alihkan secara halus ke onboarding alih-alih
            // melempar 403 mentah — pengalaman yang benar untuk user baru.
            if ($request->expectsJson()) {
                abort(403, 'No active company selected.');
            }

            return redirect()->route('onboarding');
        }

        // Verify the user belongs to this company (via owner for now, could be via roles table later)
        $ownsCompany = Company::where('id', $companyId)->where('owner_user_id', $user->id)->exists();
        $hasAccess = $ownsCompany;
        if (! $hasAccess && session()->has('admin_impersonation_id')) {
            $hasAccess = AdminImpersonationSession::where('session_id', session('admin_impersonation_id'))
                ->where('target_company_id', $companyId)
                ->where('admin_user_id', $user->id)
                ->exists();
        }

        if (! $hasAccess) {
            abort(403, 'You do not have access to this company.');
        }

        // Writer tepercaya untuk `company_role` (QA-UI-R A.3): sebelumnya tidak
        // ada penulis sama sekali, jadi seluruh cek owner di Settings/Pipeline
        // gagal untuk owner sungguhan. Di-set ulang di setiap request /app
        // setelah akses company tervalidasi, jadi nilai basi tertimpa saat
        // company aktif berganti. Admin impersonasi bukan owner usaha: admin
        // memantau, bukan mengubah konfigurasi klien.
        session(['company_role' => $ownsCompany ? 'owner' : 'staff']);

        return $next($request);
    }
}
