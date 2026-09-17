<?php

namespace App\Livewire;

use App\Contracts\CompanyContext;
use App\Contracts\PresetSource;
use App\Services\BusinessIdentityStore;
use App\Services\CompanySettingsStore;
use App\Services\SettingsTabRegistry;
use App\Services\ThemeRegistry;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;
use Throwable;

class Settings extends Component
{
    /**
     * Sepuluh pasangan kunci kamus istilah global (`INDUSTRY_PRESETS.md` §3).
     * Satu baris form mengubah bentuk tunggal dan jamak sekaligus ke nilai
     * yang sama karena Bahasa Indonesia tidak membedakan keduanya secara
     * infleksi.
     *
     * @var array<string, string>
     */
    private const TERMINOLOGY_PAIRS = [
        'contact' => 'contacts',
        'deal' => 'deals',
        'project' => 'projects',
        'resource' => 'resources',
        'booking' => 'bookings',
        'item' => 'items',
        'order' => 'orders',
        'staff' => 'staffs',
        'invoice' => 'invoices',
        'vendor' => 'vendors',
    ];

    #[Locked]
    public string $companySlug;

    #[Locked]
    public string $selectedTheme = 'a';

    #[Locked]
    public bool $canManageTheme = false;

    #[Locked]
    public string $activeTab = 'theme';

    /** @var list<array{id: string, label: string, roles: list<string>}> */
    #[Locked]
    public array $tabs = [];

    #[Locked]
    public string $selectedPreset = '';

    /** @var array<string, string> */
    public array $terminologyForm = [];

    public ?string $featuresNotice = null;

    public ?string $featuresFailure = null;

    public function mount(CompanyContext $companyContext, ?string $tab = null): void
    {
        try {
            $this->companySlug = $companyContext->current();
        } catch (InvalidArgumentException) {
            abort(404);
        } catch (LogicException) {
            abort(403);
        }

        $this->canManageTheme = session('company_role') === 'owner';
        // Peran yang belum diketahui diperlakukan sebagai staff (paling
        // terbatas yang masih berhak login), bukan tanpa tab sama sekali -
        // konsisten dengan `canManageTheme` yang juga menolak selain 'owner'
        // tanpa menutup akses baca sama sekali.
        $role = session('company_role') === 'owner' ? 'owner' : 'staff';
        $this->tabs = app(SettingsTabRegistry::class)->visibleTo($role);

        $requestedTab = $tab ?? (string) request()->query('tab', 'theme');
        $this->activeTab = in_array($requestedTab, array_column($this->tabs, 'id'), true)
            ? $requestedTab
            : 'theme';
        $this->selectedTheme = $this->storedTheme();

        // Preset company mungkin belum lengkap (mis. fixture demo tanpa
        // `business_identity.json`/`settings.json['preset']`) - tab lain
        // (Tema, Profil) tetap harus bisa dirender. Tab Fitur Bisnis
        // menampilkan dropdown tanpa opsi terpilih bila ini terjadi.
        try {
            $this->selectedPreset = $companyContext->preset();
        } catch (InvalidArgumentException) {
            $this->selectedPreset = '';
        }

        $this->refreshTerminologyForm();
    }

    public function selectTheme(string $theme, CompanyContext $companyContext): void
    {
        $this->assertOwnerOfActiveCompany($companyContext);
        abort_unless(ThemeRegistry::has($theme), 404);

        app(CompanySettingsStore::class)->update(
            $this->companySlug,
            fn (array $settings): array => [...$settings, 'theme' => $theme],
        );

        $this->selectedTheme = $theme;
        $this->dispatch('theme-changed', theme: $theme);
    }

    public function updatePreset(string $preset, CompanyContext $companyContext, PresetSource $presets): void
    {
        $this->resetFeaturesFeedback();
        $activeCompany = $this->assertOwnerOfActiveCompany($companyContext);
        abort_if($presets->find($preset) === null, 404);

        app(CompanySettingsStore::class)->update(
            $activeCompany,
            fn (array $settings): array => [...$settings, 'preset' => $preset],
        );

        $this->selectedPreset = $preset;
        $this->refreshTerminologyForm();
        $this->featuresNotice = 'Preset bisnis diperbarui.';
    }

    public function updateTerminology(string $key, CompanyContext $companyContext): void
    {
        $this->resetFeaturesFeedback();
        $activeCompany = $this->assertOwnerOfActiveCompany($companyContext);
        abort_unless(array_key_exists($key, self::TERMINOLOGY_PAIRS), 404);

        $value = trim((string) ($this->terminologyForm[$key] ?? ''));
        if ($value === '') {
            $this->featuresFailure = 'Label istilah tidak boleh kosong.';
            $this->refreshTerminologyForm();

            return;
        }

        $plural = self::TERMINOLOGY_PAIRS[$key];

        app(CompanySettingsStore::class)->update(
            $activeCompany,
            function (array $settings) use ($key, $plural, $value): array {
                $terminology = is_array($settings['terminology'] ?? null) ? $settings['terminology'] : [];
                $terminology[$key] = $value;
                $terminology[$plural] = $value;
                $settings['terminology'] = $terminology;

                return $settings;
            },
        );

        $this->refreshTerminologyForm();
        $this->featuresNotice = 'Istilah tersimpan.';
    }

    public function render(PresetSource $presets)
    {
        return view('livewire.settings', [
            'themes' => ThemeRegistry::themes(),
            'presets' => $presets->all(),
            'workflows' => $this->currentWorkflows($presets),
            'terminologyPairs' => self::TERMINOLOGY_PAIRS,
            'businessSummary' => $this->businessSummary(),
        ])->layout('components.layouts.module', [
            'theme' => $this->selectedTheme,
        ]);
    }

    /**
     * Guard identik untuk seluruh aksi owner-only tab Fitur Bisnis: company
     * aktif divalidasi ulang dari context (bukan properti Livewire yang bisa
     * basi), lalu role owner direvalidasi dari session server-side.
     */
    private function assertOwnerOfActiveCompany(CompanyContext $companyContext): string
    {
        try {
            $activeCompany = $companyContext->current();
        } catch (InvalidArgumentException) {
            abort(404);
        } catch (LogicException) {
            abort(403);
        }

        abort_unless($activeCompany === $this->companySlug, 403);
        abort_unless(session('company_role') === 'owner', 403);

        return $activeCompany;
    }

    private function refreshTerminologyForm(): void
    {
        $this->terminologyForm = [];

        try {
            foreach (self::TERMINOLOGY_PAIRS as $singular => $plural) {
                $this->terminologyForm[$singular] = term($singular);
            }
        } catch (InvalidArgumentException) {
            // Preset company belum lengkap (mis. fixture demo tanpa
            // business_identity.json) - tab lain tetap harus dapat dirender.
            $this->terminologyForm = [];
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function currentWorkflows(PresetSource $presets): array
    {
        if ($this->selectedPreset === '') {
            return [];
        }

        $definition = $presets->find($this->selectedPreset);

        return is_array($definition['workflows'] ?? null) ? $definition['workflows'] : [];
    }

    /**
     * Ringkasan identitas & profil pajak untuk tab Profil. Baca-saja; edit
     * profil bisnis di luar cakupan task ini. Dibungkus try/catch agar
     * kegagalan baca data tidak merusak seluruh halaman Pengaturan.
     *
     * @return array{name: string, preset: string, taxable: bool}|null
     */
    private function businessSummary(): ?array
    {
        try {
            $store = app(BusinessIdentityStore::class);
            $identity = $store->read($this->companySlug);
            $taxProfile = $store->taxProfile($this->companySlug);

            return [
                'name' => (string) ($identity['name'] ?? $this->companySlug),
                'preset' => (string) ($identity['preset'] ?? $this->selectedPreset),
                'taxable' => $taxProfile->taxable,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function resetFeaturesFeedback(): void
    {
        $this->featuresNotice = null;
        $this->featuresFailure = null;
    }

    private function storedTheme(): string
    {
        return ThemeRegistry::forCompany($this->companySlug);
    }
}
