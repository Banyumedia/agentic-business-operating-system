<?php

use App\Contracts\CompanyContext;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminImpersonationController;
use App\Http\Controllers\App\GroupReportController;
use App\Http\Controllers\App\ModuleController;
use App\Http\Middleware\EnsureCompanyAccess;
use App\Http\Middleware\EnsureCompanyContext;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\SetCurrentCompany;
use App\Livewire\Admin\AdminInvoiceManager;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Billing\PaymentInstructionPage;
use App\Livewire\Billing\SubscribePage;
use App\Livewire\Dashboard;
use App\Livewire\Lobby;
use App\Livewire\Onboarding;
use App\Livewire\Paywall;
use App\Livewire\Public\IndustryList;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

Route::get('/', Lobby::class)->name('lobby');
Route::get('/industri', IndustryList::class)->name('industri');

// Onboarding membuat data usaha - hanya untuk pengguna terautentikasi.
Route::middleware('auth')->group(function (): void {
    Route::get('/onboarding', Onboarding::class)->name('onboarding');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

// Logout harus POST + CSRF (QA-UI-R A.5) - GET logout memungkinkan logout
// lintas situs lewat link/gambar.
Route::middleware('auth')->post('/logout', function () {
    auth()->guard()?->logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect(route('login'));
})->name('logout');

Route::middleware(['auth', SetCurrentCompany::class, EnsureCompanyAccess::class])->group(function (): void {
    Route::middleware(EnsureCompanyContext::class)->group(function (): void {
        Route::get('/app/dashboard', Dashboard::class)->name('app.dashboard');
        // Paywall D-61: kuota gratis habis → klien memilih paket berbayar.
        Route::get('/app/paywall', Paywall::class)->name('app.paywall');
        Route::get('/app/group-report', [GroupReportController::class, 'show'])->name('app.group_report');
        Route::get('/app/settings', Settings::class)->name('app.settings');
        Route::get('/app/settings/{tab}', Settings::class)
            ->whereIn('tab', ['profile', 'theme', 'features', 'assistant', 'usage', 'team', 'export', 'erasure'])
            ->name('app.settings.tab');

        Route::get('/app/settings/export/download', function () {
            if (session()->has('admin_impersonation_id')) {
                abort(403, 'Admin impersonation tidak dapat mengunduh data klien.');
            }

            $company = app(CompanyContext::class)->getCompany();
            if ((int) auth()->id() !== (int) $company->owner_user_id) {
                abort(403, 'Hanya owner yang dapat mengunduh data usaha.');
            }

            $path = storage_path('app/exports/'.$company->id.'/export.zip');
            if (file_exists($path)) {
                return response()->download($path);
            }

            abort(404, 'Export not found');
        })->name('settings.export.download');

        // PAY-1: Halaman pilih paket dan instruksi pembayaran manual
        Route::get('/app/billing/subscribe', SubscribePage::class)->name('billing.subscribe');
        Route::get('/app/billing/payment-instruction/{invoice}', PaymentInstructionPage::class)->name('billing.payment-instruction');

        Route::get('/app/{module}/{submodule?}', [ModuleController::class, 'show'])
            ->middleware(EnsureFeatureEnabled::class)
            ->name('app.module');
    });
});

Route::middleware(['auth', RequireSuperAdmin::class])->prefix('admin')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/impersonate/stop', [AdminImpersonationController::class, 'stop'])->name('admin.impersonate.stop');
    Route::post('/impersonate/{company}', [AdminImpersonationController::class, 'impersonate'])->name('admin.impersonate');
    // PAY-1: Admin invoice management
    Route::get('/invoices', AdminInvoiceManager::class)->name('admin.invoices');
});
