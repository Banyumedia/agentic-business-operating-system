<?php

namespace App\Livewire;

use App\Services\CompanySettingsStore;
use App\Services\ThemeRegistry;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Settings extends Component
{
    #[Locked]
    public string $companySlug;

    #[Locked]
    public string $selectedTheme = 'a';

    #[Locked]
    public bool $canManageTheme = false;

    #[Locked]
    public string $activeTab = 'theme';

    /** @var array<int, array{id: string, label: string}> */
    #[Locked]
    public array $tabs = [
        ['id' => 'profile', 'label' => 'Profil Bisnis & Pajak'],
        ['id' => 'theme', 'label' => 'Tampilan & Tema'],
        ['id' => 'features', 'label' => 'Fitur Bisnis'],
        ['id' => 'assistant', 'label' => 'Karyawan AI'],
        ['id' => 'usage', 'label' => 'Penggunaan & Paket'],
        ['id' => 'team', 'label' => 'Tim & Akses'],
    ];

    public function mount(): void
    {
        $activeCompany = session('active_company');
        $requestedCompany = request()->query('company');
        $company = (string) ($requestedCompany ?? $activeCompany ?? 'usaha-demo');

        abort_unless((bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $company), 404);
        abort_if(
            is_string($activeCompany) && $requestedCompany !== null && $company !== $activeCompany,
            403,
        );
        abort_if(
            $activeCompany === null && $requestedCompany !== null && session('company_role') !== 'owner',
            403,
        );

        $this->companySlug = $company;
        session(['active_company' => $company]);
        $this->canManageTheme = session('company_role') === 'owner';
        $requestedTab = (string) request()->query('tab', 'theme');
        $this->activeTab = in_array($requestedTab, array_column($this->tabs, 'id'), true)
            ? $requestedTab
            : 'theme';
        $this->selectedTheme = $this->storedTheme();
    }

    public function selectTheme(string $theme): void
    {
        abort_unless($this->canManageTheme, 403);
        abort_unless(ThemeRegistry::has($theme), 404);

        app(CompanySettingsStore::class)->update(
            $this->companySlug,
            fn (array $settings): array => [...$settings, 'theme' => $theme],
        );

        $this->selectedTheme = $theme;
        $this->dispatch('theme-changed', theme: $theme);
    }

    public function render()
    {
        return view('livewire.settings', [
            'themes' => ThemeRegistry::themes(),
        ])->layout('components.layouts.module', [
            'theme' => $this->selectedTheme,
        ]);
    }

    private function storedTheme(): string
    {
        return ThemeRegistry::forCompany($this->companySlug);
    }
}
