<?php

namespace App\Http\Controllers\Api\TenantBot;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Company;
use App\Services\Ai\AiDataSharingPolicy;
use App\Services\Knowledge\BusinessNoteSearch;
use App\Services\Knowledge\BusinessNoteWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Basis pengetahuan usaha untuk bot (T-107): SOP, catatan pelanggan,
 * kesepakatan harga - hal yang membuat bot terdengar mengenal usaha itu.
 *
 * Isolasi tenant tidak ditangani di kelas ini: `AuthenticateTenantBot` sudah
 * menolak pemanggil yang profilnya tidak melayani `company_id` yang diminta,
 * dan menolak pengguna yang bukan pemilik company itu. Guard eksplisit di
 * bawah adalah lapis kedua, mengikuti pola `DocumentStandardController` (T-65).
 *
 * Catatan bertanda sensitif TIDAK PERNAH muncul di hasil pencarian bot bila
 * owner belum mengaktifkan `AiDataSharingPolicy` untuk kapabilitas terkait -
 * lihat `withheldFor()`.
 */
class KnowledgeController extends Controller
{
    public function search(Request $request, BusinessNoteSearch $search): JsonResponse
    {
        $payload = $request->validate([
            'company_id' => 'required|integer',
            'q' => 'nullable|string|max:191',
        ]);

        $company = Company::findOrFail($payload['company_id']);
        if ($error = $this->assertOwnerCaller($request, $company)) {
            return $error;
        }

        $caller = $request->attributes->get('bot_caller_user');
        $results = $search->search($company->id, (string) ($payload['q'] ?? ''));

        // T-107(f): baris sensitif tidak dikirim ke bot tanpa opt-in owner
        // eksplisit, sama seperti entitas sensitif lain (prescriptions,
        // payrolls). Ditahan seluruhnya, bukan sebagian field - judul saja
        // sudah cukup membocorkan konteksnya.
        $policy = app(AiDataSharingPolicy::class);
        $visible = [];
        $withheldCount = 0;
        foreach ($results as $result) {
            if ($result['sensitive'] && ! $this->sensitiveNoteSharingAllowed($policy, $company)) {
                $withheldCount++;

                continue;
            }
            unset($result['sensitive']);
            $visible[] = $result;
        }

        if ($results !== []) {
            AccessLog::create([
                'company_id' => $company->id,
                'user_id' => $caller->id,
                'subject_type' => 'business_notes',
                'subject_id' => null,
                'action' => 'read',
                'ip' => $request->ip(),
            ]);
        }

        return response()->json([
            'company_id' => $company->id,
            'results' => $visible,
            'withheld_sensitive_count' => $withheldCount,
        ]);
    }

    public function write(Request $request, BusinessNoteWriter $writer): JsonResponse
    {
        $payload = $request->validate([
            'company_id' => 'required|integer',
            'note_id' => 'nullable|integer',
            'title' => 'required_without:note_id|string|max:191',
            'content' => 'required|string',
            'sensitive' => 'nullable|boolean',
        ]);

        $company = Company::findOrFail($payload['company_id']);
        if ($error = $this->assertOwnerCaller($request, $company)) {
            return $error;
        }

        try {
            if (isset($payload['note_id'])) {
                // T-107(b): bot hanya MENAMBAH ke catatan yang sudah ada,
                // tidak pernah menimpanya - overwriteByOwner() adalah jalur
                // terpisah yang tidak diekspos lewat API bot sama sekali.
                $note = $writer->appendByBot($company->id, (int) $payload['note_id'], $payload['content']);
            } else {
                $note = $writer->createByBot(
                    $company->id,
                    (string) $payload['title'],
                    (string) $payload['content'],
                    (bool) ($payload['sensitive'] ?? false),
                );
            }
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'id' => $note->id,
            'author_type' => $note->author_type,
        ]);
    }

    private function assertOwnerCaller(Request $request, Company $company): ?JsonResponse
    {
        $caller = $request->attributes->get('bot_caller_user');

        if ($caller === null || (int) $company->owner_user_id !== (int) $caller->id) {
            return response()->json(['error' => 'Basis pengetahuan usaha ini tidak dapat diakses.'], 403);
        }

        return null;
    }

    /**
     * T-107(f) menyatakan catatan sensitif "mengikuti `AiDataSharingPolicy`".
     * Policy itu dikunci per kapabilitas Tier B (D-32/D-33) yang masing-masing
     * memetakan ke SATU entity tetap (prescriptions, payrolls) - business_notes
     * bukan salah satunya, dan menambah kapabilitas baru ke katalog terkunci
     * itu bukan keputusan yang boleh ditebak di sini (lihat worker report
     * T-107). Sebagai jembatan yang tidak melanggar filosofi policy (default
     * mati, opt-in eksplisit owner, per company): baris sensitif hanya
     * terlihat bila owner sudah mengaktifkan berbagi data sensitif untuk
     * SETIDAKNYA SATU kapabilitas sensitif yang sudah ada. Ini deliberately
     * konservatif (bisa menahan lebih dari yang perlu) sampai Bos memutuskan
     * apakah business_notes butuh kapabilitasnya sendiri di katalog.
     */
    private function sensitiveNoteSharingAllowed(AiDataSharingPolicy $policy, Company $company): bool
    {
        foreach ($policy->sensitiveCapabilities() as $capability) {
            if ($policy->allowsSharing($company, $capability)) {
                return true;
            }
        }

        return false;
    }
}
