<?php

namespace App\Livewire;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Services\Analytics\BusinessHealthAnalyzer;
use App\Services\CompanyRoleResolver;
use App\Services\Dashboard\DashboardComposer;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

class Dashboard extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $dashboard = null;

    /**
     * Analisis kesehatan usaha (D-50): hanya untuk owner company aktif.
     * Non-owner selalu null supaya angka finansial tidak pernah ikut ke snapshot
     * Livewire di klien - menyembunyikannya di view saja tidak cukup.
     *
     * @var array<string, mixed>|null
     */
    public ?array $health = null;

    public ?string $loadError = null;

    public bool $themeError = false;

    public function mount(
        DashboardComposer $composer,
        CompanyRoleResolver $roleResolver,
        BusinessHealthAnalyzer $analyzer,
    ): void {
        $this->loadDashboard($composer, $roleResolver, $analyzer);
    }

    public function reload(
        DashboardComposer $composer,
        CompanyRoleResolver $roleResolver,
        BusinessHealthAnalyzer $analyzer,
    ): void {
        $this->loadDashboard($composer, $roleResolver, $analyzer);
    }

    public function render(CompanyContext $companyContext, CompanySettingsStore $settingsStore): View
    {
        $theme = 'a';
        $this->themeError = false;

        try {
            $settings = $settingsStore->read($companyContext->current());
            if (is_string($settings['theme'] ?? null) && $settings['theme'] !== '') {
                $theme = $settings['theme'];
            }
        } catch (Throwable $exception) {
            // Fail-closed penyajian: tema tidak bisa dibaca -> tema default,
            // dashboard tetap dirender dengan banner kesalahan terkontrol.
            report($exception);
            $this->themeError = true;
        }

        return view('livewire.dashboard')
            ->layout('components.layouts.module', [
                'title' => 'Dashboard  Agentic BOS',
                'theme' => $theme,
            ]);
    }

    private function loadDashboard(
        DashboardComposer $composer,
        CompanyRoleResolver $roleResolver,
        BusinessHealthAnalyzer $analyzer,
    ): void {
        $this->loadError = null;

        try {
            $this->dashboard = $composer->compose();
        } catch (Throwable $exception) {
            report($exception);
            $this->dashboard = null;
            $this->loadError = 'Data dashboard belum dapat dimuat. Periksa sumber data lalu coba lagi.';
        }

        // D-50: analisis finansial hanya untuk owner. Fail-closed di dua arah -
        // bukan owner berarti null, dan kegagalan analyzer tidak ikut
        // menjatuhkan dashboard yang sudah berhasil dimuat.
        $this->health = null;

        if (! $roleResolver->isOwnerOfActiveCompany()) {
            return;
        }

        try {
            $this->health = $analyzer->analyze();
        } catch (Throwable $exception) {
            report($exception);
            $this->health = null;
        }
    }
}
