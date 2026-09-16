<?php

use App\Http\Middleware\EnsureCompanyContext;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Livewire\Dashboard;
use App\Livewire\DummyModule;
use App\Livewire\Lobby;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

Route::get('/', Lobby::class)->name('lobby');
Route::middleware(EnsureCompanyContext::class)->group(function (): void {
    Route::get('/app/dashboard', Dashboard::class)->name('app.dashboard');
    Route::get('/app/settings', Settings::class)->name('app.settings');
    Route::get('/app/settings/{tab}', Settings::class)
        ->whereIn('tab', ['profile', 'theme', 'features', 'assistant', 'usage', 'team'])
        ->name('app.settings.tab');
    Route::get('/app/{module}/{submodule?}', DummyModule::class)
        ->middleware(EnsureFeatureEnabled::class)
        ->name('app.module');
});
