<?php

namespace App\Livewire;

use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Services\PlanCapabilityGate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Onboarding form web (D-40). Wawancara AI via WA menyusul setelah node API
 * Hermes tersedia (T-17b); untuk sekarang klien mengisi nama usaha + preset
 * lewat form ini.
 *
 * PENTING (D-41): folder company yang dibuat di sini TIDAK otomatis bisa
 * diakses lewat `?company=` karena `JsonCompanyContext` fail-closed di luar
 * tiga company demo allowlist `config/datasource.php`. Ini batas yang
 * diterima sampai Fase 3 (`users.current_company_id` + auth sungguhan),
 * bukan bug untuk diperbaiki di task ini. Allowlist tidak diubah di sini.
 */
class Onboarding extends Component
{
    private const PRIVACY_POLICY_VERSION = '2026-09-18';

    public string $name = '';

    public string $preset = '';

    public bool $acceptPrivacyPolicy = false;

    public ?string $createdSlug = null;

    public ?string $failure = null;

    public function mount(PresetSource $presets): void
    {
        $available = $presets->all();
        if ($available !== []) {
            $this->preset = $available[0]['key'];
        }
    }

    public function submit(PresetSource $presets, CompanySettingsStore $settingsStore): void
    {
        $this->failure = null;
        $this->createdSlug = null;

        $name = trim($this->name);
        if ($name === '') {
            $this->failure = 'Nama usaha wajib diisi.';

            return;
        }

        if ($presets->find($this->preset) === null) {
            $this->failure = 'Preset bisnis tidak valid.';

            return;
        }

        if (! $this->acceptPrivacyPolicy) {
            $this->failure = 'Anda wajib menyetujui kebijakan privasi terlebih dahulu.';

            return;
        }

        try {
            $slug = $this->uniqueSlug($name);
        } catch (InvalidArgumentException $exception) {
            $this->failure = $exception->getMessage();

            return;
        }

        $disk = Storage::disk('company-json');
        $disk->put(
            "json/{$slug}/business_identity.json",
            json_encode([
                'id' => 1,
                'name' => $name,
                'preset' => $this->preset,
                // D-44: default aman non-PKP. Owner mengubah lewat tab Profil
                // setelah company ini reachable (lihat catatan D-41 di atas).
                'tax_mode' => 'non_taxable',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        // settings.json awal kosong tapi valid (object JSON), lewat store yang
        // sama dipakai owner mengedit tema/istilah/preset nanti.
        $settingsStore->update($slug, fn (array $settings): array => $settings);

        $owner = auth()->user();
        if ($owner) {
            $company = Company::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'owner_user_id' => $owner->id,
                    'business_preset' => $this->preset,
                    'theme' => 'a',
                    'is_active' => true,
                ],
            );

            $company->forceFill([
                'privacy_accepted_at' => Carbon::now(),
                'privacy_accepted_by_user_id' => $owner->id,
                'privacy_policy_version' => self::PRIVACY_POLICY_VERSION,
            ])->save();
        }

        $this->createdSlug = $slug;
        $this->name = '';
        $this->acceptPrivacyPolicy = false;
    }

    public function render(PresetSource $presets, PlanCapabilityGate $planGate): View
    {
        // Onboarding doesn't have an active company yet, so it can't evaluate plan capabilities via PlanCapabilityGate
        // We evaluate plan capabilities manually here for the onboarding form
        $planAllowed = [];
        $isPlanActive = false;

        $user = auth()->user();
        if ($user) {
            // Find user's active primary membership plan (this is simplified as we don't know the exact company context yet,
            // but for D-52 "saat onboarding" this is needed)
            $membership = CompanyMembership::with('plan')
                ->whereHas('company', fn ($q) => $q->where('owner_user_id', $user->id))
                ->where('status', 'active')
                ->first();

            if ($membership && $membership->plan && is_array($membership->plan->features)) {
                $planAllowed = $membership->plan->features;
                $isPlanActive = true;
            }
        }

        $presetOptions = array_map(function (array $p) use ($isPlanActive, $planAllowed) {
            $missing = [];
            if ($isPlanActive) {
                foreach (array_keys(array_filter($p['capabilities'])) as $cap) {
                    if (! in_array($cap, $planAllowed, true)) {
                        $missing[] = $cap;
                    }
                }
            }

            return [
                'key' => $p['key'],
                'name' => $p['name'],
                'missing' => $missing,
            ];
        }, $presets->all());

        return view('livewire.onboarding', [
            'presets' => $presetOptions,
            'privacyPolicyVersion' => self::PRIVACY_POLICY_VERSION,
        ])->layout('layouts.app');
    }

    /**
     * Slug unik berbasis nama usaha. Folder yang sudah ada (identitas usaha
     * sudah tertulis) membuat slug bertambah `-2`, `-3`, dst - tidak pernah
     * menimpa data company lain.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            throw new InvalidArgumentException('Nama usaha tidak dapat dijadikan identitas.');
        }

        $disk = Storage::disk('company-json');
        $slug = $base;
        $suffix = 2;
        while ($disk->exists("json/{$slug}/business_identity.json")) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
