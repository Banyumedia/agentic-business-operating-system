<?php

namespace App\Livewire;

use App\Contracts\CompanyContext;
use App\Services\CompanySettingsStore;
use App\Services\ThemeRegistry;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;

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

    public function mount(CompanyContext $companyContext): void
    {
        try {
            $this->companySlug = $companyContext->current();
        } catch (InvalidArgumentException) {
            abort(404);
        } catch (LogicException) {
            abort(403);
        }

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
