<?php

use App\Contracts\CompanyContext;
use App\Http\Middleware\EnsureCompanyAccess;
use App\Http\Middleware\EnsureCompanyContext;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\SetCurrentCompany;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\DummyModule;
use App\Livewire\Lobby;
use App\Livewire\Onboarding;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

Route::get('/', Lobby::class)->name('lobby');
Route::get('/onboarding', Onboarding::class)->name('onboarding');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware(['auth', SetCurrentCompany::class, EnsureCompanyAccess::class])->group(function (): void {
    Route::middleware(EnsureCompanyContext::class)->group(function (): void {
        Route::get('/app/dashboard', Dashboard::class)->name('app.dashboard');
        Route::get('/app/settings', Settings::class)->name('app.settings');
        Route::get('/app/settings/{tab}', Settings::class)
            ->whereIn('tab', ['profile', 'theme', 'features', 'assistant', 'usage', 'team', 'export'])
            ->name('app.settings.tab');

        Route::get('/app/settings/export/download', function () {
            $companyId = app(CompanyContext::class)->current();
            $path = storage_path('app/exports/'.$companyId.'/export.zip');
            if (file_exists($path)) {
                return response()->download($path);
            }
            abort(404, 'Export not found');
        })->name('settings.export.download');

        Route::get('/app/{module}/{submodule?}', DummyModule::class)
            ->middleware(EnsureFeatureEnabled::class)
            ->name('app.module');
    });
});
