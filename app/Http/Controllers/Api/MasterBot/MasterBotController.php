<?php

namespace App\Http\Controllers\Api\MasterBot;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;

class MasterBotController extends Controller
{
    /**
     * Mengubah nominal rupiah menjadi jumlah token dari mapping konfigurasi.
     *
     * Dibulatkan ke bawah: pelanggan tidak pernah dikreditkan lebih dari yang ia
     * bayar. Rasionya di `config/billing.php` supaya harga token bisa disetel tanpa
     * menyentuh kode.
     */
    private function tokensForAmount(float $amount): int
    {
        $rate = (float) config('billing.topup.tokens_per_rupiah', 1);

        return (int) floor($amount * $rate);
    }

    private function resolveCompany(Request $request)
    {
        $waNumber = $request->input('wa_number');

        if (! $waNumber) {
            abort(400, 'wa_number is required');
        }

        $user = User::where('wa_number', $waNumber)->first();

        if (! $user || ! $user->current_company_id) {
            abort(403, 'User does not belong to any company');
        }

        return Company::findOrFail($user->current_company_id);
    }

    public function createTicket(Request $request)
    {
        $payload = $request->validate([
            'wa_number' => 'required|string',
            'subject' => 'required|string',
            'description' => 'required|string',
        ]);

        $company = $this->resolveCompany($request);
        $user = User::where('wa_number', $payload['wa_number'])->first();

        $ticket = SupportTicket::create([
            'company_id' => $company->id,
            'reported_by_user_id' => $user->id,
            'ticket_number' => 'TKT-'.time().'-'.rand(1000, 9999),
            'subject' => $payload['subject'],
            'description' => $payload['description'],
        ]);

        return response()->json([
            'status' => 'success',
            'ticket_id' => $ticket->ticket_number,
        ]);
    }

    public function checkBalance(Request $request)
    {
        $request->validate([
            'wa_number' => 'required|string',
        ]);

        $company = $this->resolveCompany($request);
        $membership = $company->memberships()->first();

        if (! $membership) {
            return response()->json(['error' => 'No active membership found'], 404);
        }

        return response()->json([
            'balance' => $membership->current_token_balance,
            'status' => $membership->status,
        ]);
    }

    /**
     * Mencatat prospek yang japri bot CS platform (D-76).
     *
     * Berbeda dari `createTicket`: **tidak** memanggil `resolveCompany()`, jadi
     * pemanggil yang belum punya company - yaitu semua calon pelanggan - tetap
     * dicatat. Itu inti keberadaan jalur ini; melonggarkan `createTicket` sendiri
     * akan membuka jalur tiket tenant untuk pemanggil tanpa company, yang bukan
     * yang diinginkan.
     *
     * Nomor dinormalkan lewat `User::normalizeWaNumber()` - sumber tunggal yang
     * sama dengan consumer WA lain (BS-03), supaya `08...` dan `628...` menjadi satu
     * prospek, bukan dua. Nomor jadi kunci: pesan berikutnya dari orang yang sama
     * memperbarui kartunya, bukan menumpuk baris.
     *
     * Tidak ada eksekusi di sini. Prospek yang minta tindakan (dibuatkan akun,
     * dijadwalkan demo) dicatat pesannya; tindakannya masuk antrean sebagai tugas
     * manusia, bukan dijalankan bot (D-76).
     */
    public function captureLead(Request $request)
    {
        $payload = $request->validate([
            'wa_number' => 'required|string',
            'message' => 'required|string',
            'name' => 'nullable|string|max:191',
            'source' => 'nullable|string|max:32',
        ]);

        $normalized = User::normalizeWaNumber($payload['wa_number']);

        if ($normalized === '') {
            return response()->json(['error' => 'Nomor WhatsApp tidak dapat dibaca.'], 422);
        }

        // Data disimpan minimal (D-76). `updateOrCreate` pada nomor: satu prospek
        // satu kartu, pesan terakhir menang. `name`/`source` hanya ditimpa bila
        // dikirim, supaya nilai yang sudah ada tidak terhapus oleh pesan lanjutan
        // yang tak menyebutkannya.
        $attributes = ['last_message' => $payload['message']];

        if (! empty($payload['name'])) {
            $attributes['name'] = $payload['name'];
        }

        if (! empty($payload['source'])) {
            $attributes['source'] = $payload['source'];
        }

        $lead = Lead::updateOrCreate(['wa_number' => $normalized], $attributes);

        return response()->json([
            'status' => 'success',
            'lead_id' => $lead->id,
        ]);
    }

    public function createTopupInvoice(Request $request)
    {
        $payload = $request->validate([
            'wa_number' => 'required|string',
            // `gt:0` ditegakkan di pintu masuk: nominal nol/negatif tidak punya arti
            // sebagai pembelian token, dan meloloskannya hanya menunda penolakan
            // sampai settlement (BS-02).
            'amount' => 'required|numeric|gt:0',
        ]);

        $company = $this->resolveCompany($request);
        $membership = $company->memberships()->first();

        if (! $membership) {
            return response()->json(['error' => 'No active membership found'], 404);
        }

        // Grant token diturunkan dari nominal di sini, saat invoice dibuat - bukan
        // dikarang saat settlement. Inilah yang menutup fallback `?? 1` di webhook:
        // begitu grant selalu terisi, webhook bisa menolak invoice tanpa grant
        // sebagai kesalahan, bukan menebak angka.
        $tokenGrant = $this->tokensForAmount((float) $payload['amount']);

        if ($tokenGrant < 1) {
            return response()->json(['error' => 'Nominal terlalu kecil untuk dikonversi menjadi token.'], 422);
        }

        $invoice = $company->invoices()->create([
            'company_membership_id' => $membership->id,
            'type' => 'topup',
            'order_id' => 'TOPUP-'.time().'-'.rand(1000, 9999),
            'amount' => $payload['amount'],
            'token_amount_granted' => $tokenGrant,
            'payment_status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'invoice_id' => $invoice->order_id,
            'payment_url' => 'https://fake-payment-gateway.com/pay/'.$invoice->order_id,
        ]);
    }
}
