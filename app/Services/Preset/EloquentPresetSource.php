<?php

namespace App\Services\Preset;

use App\Contracts\PresetSource;
use App\Models\BusinessPreset;

class EloquentPresetSource implements PresetSource
{
    public function all(): array
    {
        return BusinessPreset::query()
            ->orderBy('key')
            ->pluck('definition')
            ->toArray();
    }

    public function find(string $key): ?array
    {
        $preset = BusinessPreset::find($key);

        return $preset?->definition;
    }
}
