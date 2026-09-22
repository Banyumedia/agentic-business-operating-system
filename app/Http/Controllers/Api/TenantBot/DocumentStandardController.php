<?php

namespace App\Http\Controllers\Api\TenantBot;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Documents\DocumentStandardStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Standar dokumen per tenant untuk dibaca skill (T-65, D-69).
 *
 * **Baca-saja bagi bot.** Menyetel standar adalah keputusan owner di web, bukan
 * sesuatu yang bot boleh ubah sendiri — karena itu tidak ada aksi tulis di sini,
 * dan rute tulisnya memang tidak didaftarkan.
 *
 * Isolasi tenant tidak ditangani di kelas ini: `AuthenticateTenantBot` sudah
 * menolak pemanggil yang profilnya tidak melayani `company_id` yang diminta, dan
 * menolak pengguna yang bukan pemilik company itu. Guard di bawah adalah lapis
 * kedua yang eksplisit, bukan satu-satunya.
 */
class DocumentStandardController extends Controller
{
    public function show(Request $request, DocumentStandardStore $store): JsonResponse
    {
        $payload = $request->validate([
            'company_id' => 'required|integer',
        ]);

        $company = Company::findOrFail($payload['company_id']);
        $caller = $request->attributes->get('bot_caller_user');

        if ($caller === null || (int) $company->owner_user_id !== (int) $caller->id) {
            return response()->json(['error' => 'Standar dokumen usaha ini tidak dapat diakses.'], 403);
        }

        $result = $store->forCompany($company);

        return response()->json([
            'company_id' => $company->id,
            'standards' => $result['standards'],
            'is_default' => $result['is_default'],
        ]);
    }
}
