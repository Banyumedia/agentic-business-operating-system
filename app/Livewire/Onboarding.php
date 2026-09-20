<?php

namespace App\Livewire;

use App\Contracts\CompanyContext;
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
use Throwable;

/**
 * Onboarding form web (D-40) dalam alur multi-langkah (maraton UX lane A):
 * 1) identitas usaha, 2) pilih preset dari PresetSource/registry,
 * 3) ringkasan + persetujuan kebijakan privasi -> usaha dibuat dan owner
 * diarahkan ke dashboard.
 *
 * Wawancara AI via WA menyusul setelah node API Hermes tersedia (T-17b).
 *
 * PENTING (D-41): folder company yang dibuat di sini TIDAK otomatis bisa
 * diakses lewat `?company=` karena `JsonCompanyContext` fail-closed di luar
 * tiga company demo allowlist `config/datasource.php`. Setelah submit sukses
 * konteks company aktif di-set via kontrak `CompanyContext` (driver-agnostik),
 * lalu redirect ke dashboard.
 */
class Onboarding extends Component
{
    private const PRIVACY_POLICY_VERSION = '2026-09-18';

    private const TOTAL_STEPS = 3;

    public int $step = 1;

    public string $name = '';

    public string $preset = '';

    public bool $acceptPrivacyPolicy = false;

    public ?string $createdSlug = null;

    public ?string $failure = null;

    public function mount(PresetSource $presets): void
    {
        // `?preset=` hanya diterima bila key ada di PresetSource (item QA-UI-R);
        // selain itu jatuh ke default aman, tidak pernah 500.
        $requested = (string) request()->query('preset', '');
        $available = $presets->all();

        $this->preset = $presets->find($requested) !== null
            ? $requested
            : ($available !== [] ? $available[0]['key'] : '');

        // Tautan kartu dari halaman publik /industri sudah memilih preset;
        // pemilik langsung diarahkan ke langkah identitas (step 1) supaya
        // alur tetap berurutan, preset tinggal dikonfirmasi di step 2.
    }

    public function nextStep(PresetSource $presets): void
    {
        $this->failure = null;

        if ($this->step === 1 && trim($this->name) === '') {
            $this->failure = 'Nama usaha wajib diisi.';

            return;
        }

        if ($this->step === 2 && $presets->find($this->preset) === null) {
            $this->failure = 'Preset bisnis tidak valid.';

            return;
        }

        if ($this->step >= self::TOTAL_STEPS) {
            return;
        }

        $this->step++;
    }

    public function previousStep(): void
    {
        $this->failure = null;

        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function submit(PresetSource $presets, CompanySettingsStore $settingsStore, CompanyContext $context): void
    {
        $this->failure = null;
        $this->createdSlug = null;

        // Re-check autentikasi sebelum tulisan pertama (route sudah memakai
        // middleware auth, tapi Livewire action tidak otomatis meneruskannya).
        $owner = auth()->user();
        if ($owner === null) {
            abort(403, 'Onboarding hanya untuk pengguna yang terautentikasi.');
        }

        $name = trim($this->name);
        if ($name === '') {
            $this->failure = 'Nama usaha wajib diisi.';
            $this->step = 1;

            return;
        }

        if ($presets->find($this->preset) === null) {
            $this->failure = 'Preset bisnis tidak valid.';
            $this->step = 2;

            return;
        }

        if (! $this->acceptPrivacyPolicy) {
            $this->failure = 'Anda wajib menyetujui kebijakan privasi terlebih dahulu.';
            $this->step = 3;

            return;
        }

        try {
            $slug = $this->uniqueSlug($name);
        } catch (InvalidArgumentException $exception) {
            $this->failure = $exception->getMessage();
            $this->step = 1;

            return;
        }

        $company = Company::create([
            'slug' => $slug,
            'name' => $name,
            'owner_user_id' => $owner->id,
            'business_preset' => $this->preset,
            'theme' => 'a',
            'is_active' => true,
            'privacy_accepted_at' => Carbon::now(),
            'privacy_accepted_by_user_id' => $owner->id,
            'privacy_policy_version' => self::PRIVACY_POLICY_VERSION,
        ]);

        $disk = Storage::disk('company-json');
        try {
            $disk->put(
                "json/{$slug}/business_identity.json",
                json_encode([
                    'id' => 1,
                    'name' => $name,
                    'preset' => $this->preset,
                    'tax_mode' => 'non_taxable',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
            );
            $settingsStore->update($slug, fn (array $settings): array => $settings);
        } catch (Throwable $exception) {
            $disk->deleteDirectory("json/{$slug}");
            $company->forceDelete();

            throw $exception;
        }

        $this->createdSlug = $slug;
        $this->name = '';
        $this->acceptPrivacyPolicy = false;

        // Set konteks company aktif (kontrak driver-agnostik: JSON demo atau
        // Eloquent `users.current_company_id`) lalu arahkan owner ke dashboard.
        // `JsonCompanyContext` hanya menerima company demo allowlist (D-41) -
        // kegagalan set konteks tidak boleh membatalkan pembuatan usaha yang
        // sudah sah tersimpan; redirect tetap dilakukan.
        try {
            $context->setCurrent((string) $company->id);
        } catch (InvalidArgumentException) {
            // D-41: company baru memang belum reachable di driver JSON demo.
        }

        $this->redirect(route('app.dashboard'));
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
            'totalSteps' => self::TOTAL_STEPS,
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
        while ($disk->exists("json/{$slug}/business_identity.json") || Company::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
