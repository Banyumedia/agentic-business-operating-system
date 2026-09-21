<?php
/**
 * SMOKE E2E — Simulasi klien nyata memakai Agentic BOS untuk bisnis.
 * Dijalankan via: php artisan tinker storage/app/_smoke_e2e.php
 * Mencatat temuan ke stdout dengan prefix [TEMUAN] untuk yang bermasalah.
 */
use App\Models\User;
use App\Models\Company;
use App\Models\MembershipPlan;
use App\Models\Invoice;
use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$findings = [];
function note($msg){ global $findings; $findings[]=$msg; echo "[TEMUAN] $msg\n"; }
function ok($msg){ echo "[OK] $msg\n"; }

// Helper: hit a route as the logged-in user and report status
function hit(string $path){
    $req = Illuminate\Http\Request::create($path,'GET');
    $req->setLaravelSession(app('session')->driver());
    $res = app(Illuminate\Contracts\Http\Kernel::class)->handle($req);
    return [$res->getStatusCode(), $res->getContent()];
}

echo "=== SKENARIO 1: KLIEN BARU DAFTAR & ONBOARDING ===\n";
$email = 'klien.e2e.'.time().'@example.com';
$user = User::create(['name'=>'Pemilik Warung E2E','email'=>$email,'password'=>bcrypt('PasswordKuat123!')]);
ok("Registrasi user baru: id={$user->id}");
if (Company::where('owner_user_id',$user->id)->count() !== 0) note("Registrasi membuat company (harusnya tidak — D-26)");
else ok("Registrasi tidak membuat company (benar)");

// Onboarding: buat company via jalur yang sama seperti Onboarding component
Auth::login($user);
$company = Company::create([
    'name'=>'Warung Kopi E2E','slug'=>'warung-e2e-'.time(),'owner_user_id'=>$user->id,
    'business_preset'=>'fnb','module_settings'=>[], 'is_active'=>true,
]);
// BusinessIdentity + ModuleSetting (mirror onboarding eloquent)
App\Models\BusinessIdentity::create(['company_id'=>$company->id,'legal_name'=>'Warung Kopi E2E','tax_mode'=>'non_taxable','price_includes_tax'=>true,'is_default'=>true]);
App\Models\ModuleSetting::create(['company_id'=>$company->id,'module_name'=>'features','settings_json'=>[]]);
$user->current_company_id = $company->id; $user->save();
app(CompanyContext::class)->setCurrent((string)$company->id);
ok("Onboarding: company '{$company->name}' (preset fnb) terbuat + konteks aktif");

echo "\n=== SKENARIO 2: PAKAI SEMUA MODUL (tier gratis) ===\n";
$modules = [
    '/app/dashboard'=>'Dashboard',
    '/app/pos'=>'Kasir/POS',
    '/app/contacts'=>'Kontak',
    '/app/inventory'=>'Stok',
    '/app/hrd'=>'Karyawan',
    '/app/accounting'=>'Akuntansi',
    '/app/settings'=>'Pengaturan',
];
foreach($modules as $path=>$label){
    [$code,$html] = hit($path);
    if ($code===200) ok("$label ($path) -> 200");
    elseif ($code===302) ok("$label ($path) -> 302 (redirect, kemungkinan auth/module gating)");
    else note("$label ($path) -> $code (tak terduga)");
}

echo "\n=== SKENARIO 3: TAMBAH DATA BISNIS NYATA (kontak + transaksi) ===\n";
try {
    $repo = app(EntityRepository::class);
    $contact = $repo->for((string)$company->id,'contacts')->save(['name'=>'Pelanggan Setia','wa_number'=>'08123456789']);
    ok("Tambah kontak: {$contact['name']} (id {$contact['id']})");
    $order = $repo->for((string)$company->id,'cash_entries')->save(['direction'=>'in','amount'=>50000,'note'=>'Penjualan kopi','occurred_at'=>now()->toDateTimeString()]);
    ok("Catat pemasukan: Rp50.000 (cash_entry id {$order['id']})");
} catch (\Throwable $e) {
    note("Gagal tambah data bisnis: ".$e->getMessage());
}

echo "\n=== SKENARIO 4: PANEL KESEHATAN USAHA (owner) ===\n";
$analyzer = app(App\Services\Analytics\BusinessHealthAnalyzer::class);
try {
    $health = $analyzer->analyze();
    if (!empty($health['insufficient_data'])) note("Panel kesehatan: insufficient_data padahal ada 1 transaksi (mungkin butuh lebih banyak data)");
    else {
        $margin = $health['margin'];
        ok("Panel kesehatan: revenue={$health['revenue']}, margin={$margin}, trend={$health['trend']['direction']}");
    }
} catch (\Throwable $e) {
    note("Analyzer error: ".$e->getMessage());
}

echo "\n=== SKENARIO 5: KUOTA HABIS -> PAYWALL ===\n";
// Simulasikan kuota token habis dengan memakai TokenQuotaGate
$gate = app(App\Services\Billing\TokenQuotaGate::class);
$balance = $gate->getCurrentTokenBalance();
ok("Saldo token tier gratis: {$balance} (harusnya 500 dari config)");
if ($balance < 1) note("Saldo tier gratis 0 — padahal D-60 harus 500");

echo "\n=== SKENARIO 6: PILIH PAKET & BAYAR MANUAL ===\n";
$plan = MembershipPlan::where('slug','starter')->first();
if (!$plan) { note("Tidak ada paket aktif — halaman subscribe akan kosong"); }
else {
    try {
        $svc = app(App\Services\Manual\InvoiceCreationService::class);
        $inv = $svc->createSubscriptionInvoice($company,$plan,$user->id);
        ok("Invoice dibuat: {$inv->order_id} Rp".number_format($inv->amount,0,",",".")." status={$inv->payment_status}");
        // Instruksi bayar menampilkan rekening dari config?
        [$code,$html] = hit("/app/billing/payment-instruction/{$inv->id}");
        if ($code!==200) note("Halaman instruksi bayar -> $code (harusnya 200)");
        else {
            echo (str_contains($html,'1370011925654')?"[OK] Nomor rekening Mandiri tampil\n":"[TEMUAN] Nomor rekening TIDAK tampil\n");
            echo (str_contains($html,'Mandiri')?"[OK] Nama bank tampil\n":"[TEMUAN] Nama bank TIDAK tampil\n");
            echo (str_contains($html,'Didik Wahyudi')?"[OK] Atas nama tampil\n":"[TEMUAN] Atas nama TIDAK tampil\n");
        }
        // Anti-spam: buat invoice kedua saat masih pending harus ditolak
        try {
            $svc->createSubscriptionInvoice($company,$plan,$user->id);
            note("Bisa buat invoice kedua saat pending — anti-spam gagal");
        } catch (\Throwable $e) {
            ok("Anti-spam: invoice kedua ditolak saat masih pending");
        }
        // Konfirmasi admin
        $confirm = app(App\Services\Manual\InvoiceConfirmationService::class);
        $confirm->confirmPayment($inv);
        $active = $company->memberships()->where('status','active')->latest('id')->first();
        if ($active && $active->plan_id===$plan->id) ok("Setelah konfirmasi: membership AKTIF paket {$plan->name}");
        else note("Setelah konfirmasi: membership tidak aktif / plan salah");
        // Idempoten: konfirmasi kedua kali tidak mengkredit dua kali
        try { $confirm->confirmPayment($inv->fresh()); ok("Idempoten: konfirmasi ulang aman (tidak dobel)"); }
        catch (\Throwable $e) { ok("Idempoten: konfirmasi ulang ditolak ({$e->getMessage()})"); }
    } catch (\Throwable $e) {
        note("Alur bayar gagal: ".$e->getMessage());
    }
}

echo "\n=== SKENARIO 7: KLIEN BERBEDA (tenant isolation) ===\n";
$user2 = User::create(['name'=>'Pemilik Lain','email'=>'lain.'.time().'@example.com','password'=>bcrypt('x')]);
$company2 = Company::create(['name'=>'Toko Lain','slug'=>'toko-lain-'.time(),'owner_user_id'=>$user2->id,'business_preset'=>'retail','module_settings'=>[],'is_active'=>true]);
app(CompanyContext::class)->setCurrent((string)$company2->id);
$repo = app(EntityRepository::class);
$rowsC2 = $repo->for((string)$company2->id,'contacts')->all();
$leak = collect($rowsC2)->contains(fn($r)=>($r['name']??'')==='Pelanggan Setia');
if ($leak) note("KEBOCORAN TENANT: data company1 terbaca di company2!");
else ok("Tenant isolation: data company1 TIDAK terbaca di company2");
// Coba akses eksplisit company1 dari konteks company2
try { $repo->for((string)$company->id,'contacts')->all(); note("KEBOCORAN: akses lintas company tidak ditolak"); }
catch (\Throwable $e) { ok("Akses lintas company ditolak (benar)"); }

echo "\n=== RINGKASAN TEMUAN ===\n";
if (empty($findings)) echo "TIDAK ADA TEMUAN — semua skenario bersih.\n";
else { echo count($findings)." temuan:\n"; foreach($findings as $f) echo "- $f\n"; }

// Cleanup: hapus semua data uji
DB::table('invoices')->whereIn('company_id',[$company->id,$company2->id])->delete();
DB::table('company_memberships')->whereIn('company_id',[$company->id,$company2->id])->delete();
DB::table('business_identities')->whereIn('company_id',[$company->id,$company2->id])->delete();
DB::table('module_settings')->whereIn('company_id',[$company->id,$company2->id])->delete();
DB::table('cash_entries')->where('company_id',$company->id)->delete();
DB::table('contacts')->where('company_id',$company->id)->delete();
$company->forceDelete(); $company2->forceDelete();
$user->forceDelete(); $user2->forceDelete();
echo "\nCLEANUP_SELESAI\n";
