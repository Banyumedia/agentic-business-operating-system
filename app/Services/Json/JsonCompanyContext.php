<?php

namespace App\Services\Json;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;
use LogicException;

class JsonCompanyContext implements CompanyContext
{
    /** @var list<string> */
    private array $allowedCompanies;

    public function __construct(private readonly CompanySettingsStore $settingsStore)
    {
        $companies = config('datasource.demo_companies', []);
        $this->allowedCompanies = is_array($companies) ? array_values($companies) : [];
    }

    public function current(): string
    {
        $this->assertDemoEnvironment();

        $requested = request()->query('company');
        if ($requested !== null) {
            if (! is_string($requested)) {
                throw new InvalidArgumentException('Company demo tidak valid.');
            }

            $this->setCurrent($requested);
        }

        $company = session('active_company');
        if (! is_string($company)) {
            throw new LogicException('Company aktif belum dipilih.');
        }

        $this->assertAllowed($company);

        return $company;
    }

    public function preset(): string
    {
        $company = $this->current();
        $settings = $this->settingsStore->read($company);
        $preset = $settings['preset'] ?? $this->identityPreset($company);

        if (! is_string($preset) || $preset === '') {
            throw new InvalidArgumentException("Preset company belum dikonfigurasi: {$company}");
        }

        return $preset;
    }

    public function displayName(): string
    {
        $company = $this->current();
        $name = $this->identityPreset($company) === null
            ? null
            : $this->identityName($company);

        // Fallback setara perilaku lama: slug di-title-case.
        return $name ?? str($company)->replace('-', ' ')->title()->toString();
    }

    private function identityName(string $company): ?string
    {
        $path = "json/{$company}/business_identity.json";
        $disk = Storage::disk('company-json');
        if (! $disk->exists($path)) {
            return null;
        }

        $identity = json_decode($disk->get($path), flags: JSON_THROW_ON_ERROR);
        if (! is_object($identity)) {
            throw new JsonException("Identitas usaha harus object: {$company}");
        }

        $name = $identity->name ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function setCurrent(string $company): void
    {
        $this->assertDemoEnvironment();
        $this->assertAllowed($company);
        session(['active_company' => $company]);
    }

    private function assertDemoEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('JsonCompanyContext hanya tersedia di environment demo.');
        }
    }

    private function assertAllowed(string $company): void
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $company)
            || ! in_array($company, $this->allowedCompanies, true)) {
            throw new InvalidArgumentException('Company demo tidak diizinkan.');
        }
    }

    private function identityPreset(string $company): mixed
    {
        $path = "json/{$company}/business_identity.json";
        $disk = Storage::disk('company-json');
        if (! $disk->exists($path)) {
            return null;
        }

        $identity = json_decode($disk->get($path), flags: JSON_THROW_ON_ERROR);
        if (! is_object($identity)) {
            throw new JsonException("Identitas usaha harus object: {$company}");
        }

        return $identity->preset ?? null;
    }
}
