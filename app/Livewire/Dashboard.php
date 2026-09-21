<?php

namespace App\Livewire;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Services\Dashboard\DashboardComposer;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

class Dashboard extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $dashboard = null;

    public ?string $loadError = null;

    public bool $themeError = false;

    public function mount(DashboardComposer $composer): void
    {
        $this->loadDashboard($composer);
    }

    public function reload(DashboardComposer $composer): void
    {
        $this->loadDashboard($composer);
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

    private function loadDashboard(DashboardComposer $composer): void
    {
        $this->loadError = null;

        try {
            $this->dashboard = $composer->compose();
        } catch (Throwable $exception) {
            report($exception);
            $this->dashboard = null;
            $this->loadError = 'Data dashboard belum dapat dimuat. Periksa sumber data lalu coba lagi.';
        }
    }
}
