<?php

namespace App\Livewire;

use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use Illuminate\Contracts\View\View;
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
    public string $name = '';

    public string $preset = '';

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

        $this->createdSlug = $slug;
        $this->name = '';
    }

    public function render(PresetSource $presets): View
    {
        return view('livewire.onboarding', [
            'presets' => $presets->all(),
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
