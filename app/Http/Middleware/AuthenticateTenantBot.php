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

        // Yang tersimpan adalah **hash** token, bukan tokennya (QA-08). Plaintext
        // sengaja tidak diterima sebagai cadangan: menerima keduanya berarti tidak
        // mengamankan apa pun, dan baris lama yang masih plaintext memang harus
        // berhenti bekerja sampai tokennya diterbitkan ulang.
        $profile = HermesProfile::findByBotToken($token);
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

        // D-66: nomor WA berpindah tangan, jadi kecocokan nomor saja BUKAN
        // bukti identitas. Lookup wajib nomor yang sudah `wa_is_verified = true`
        // dan dinormalkan (`08...` ↔ `628...`) agar sejajar dengan consumer WA
        // lain (WhatsAppSenderIdentity, WhatsAppInteractionFilter). Sebelumnya
        // middleware ini lebih longgar: bearer sah + nomor kebetulan cocok tapi
        // belum terverifikasi tetap lolos, dan nomor sah beda format ditolak.
        // Keduanya digabung di User::findVerifiedByWaNumber() supaya tidak ada
        // salinan logika normalisasi yang bisa menyimpang.
        $user = User::findVerifiedByWaNumber($waNumber);
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
