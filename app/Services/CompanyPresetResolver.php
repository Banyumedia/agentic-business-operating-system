<?php

namespace App\Services;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use InvalidArgumentException;

class CompanyPresetResolver
{
    /** @var array<string, array{settings: array<string, mixed>, preset: array<string, mixed>}> */
    private array $cache = [];

    public function __construct(
        private readonly CompanyContext $context,
        private readonly CompanySettingsStore $settingsStore,
        private readonly PresetSource $presetSource,
    ) {}

    /** @return array{settings: array<string, mixed>, preset: array<string, mixed>} */
    public function current(): array
    {
        $company = $this->context->current();

        return $this->cache[$company] ??= $this->load($company);
    }

    public function flushCache(): void
    {
        $this->cache = [];
    }

    /** @return array{settings: array<string, mixed>, preset: array<string, mixed>} */
    private function load(string $company): array
    {
        $settings = $this->settingsStore->read($company);
        $presetKey = $this->context->preset();
        if (! is_string($presetKey) || $presetKey === '') {
            throw new InvalidArgumentException("Preset company belum dikonfigurasi: {$company}");
        }

        $preset = $this->presetSource->find($presetKey);
        if ($preset === null) {
            throw new InvalidArgumentException("Preset company tidak tersedia: {$presetKey}");
        }

        return ['settings' => $settings, 'preset' => $preset];
    }
}
