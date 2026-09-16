<?php

namespace App\Services\Json;

use App\Contracts\PresetSource;
use App\Services\Preset\PresetDefinitionValidator;
use JsonException;
use RuntimeException;

class JsonPresetSource implements PresetSource
{
    public function __construct(
        private readonly PresetDefinitionValidator $validator,
        private readonly ?string $directory = null,
    ) {}

    public function all(): array
    {
        $directory = $this->directory ?? database_path('presets');
        if (! is_dir($directory) || ! is_readable($directory)) {
            throw new RuntimeException("Direktori preset tidak tersedia: {$directory}");
        }

        $paths = glob(rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'*.json');
        if ($paths === false) {
            throw new RuntimeException("Direktori preset tidak dapat dibaca: {$directory}");
        }

        $presets = [];
        foreach ($paths as $path) {
            $definition = $this->load($path);
            $filename = pathinfo($path, PATHINFO_FILENAME);
            if ($definition['key'] !== $filename) {
                throw new JsonException("Key preset harus sama dengan nama file: {$filename}");
            }
            if (isset($presets[$definition['key']])) {
                throw new JsonException("Key preset duplikat: {$definition['key']}");
            }
            $presets[$definition['key']] = $definition;
        }

        ksort($presets);

        return array_values($presets);
    }

    public function find(string $key): ?array
    {
        foreach ($this->all() as $preset) {
            if ($preset['key'] === $key) {
                return $preset;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function load(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Preset tidak dapat dibaca: {$path}");
        }
        if (! str_starts_with(ltrim($contents), '{')) {
            throw new JsonException("Root preset harus object: {$path}");
        }

        $definition = json_decode($contents, flags: JSON_THROW_ON_ERROR);
        if (! is_object($definition)) {
            throw new JsonException("Root preset harus object: {$path}");
        }

        return $this->validator->validate($definition);
    }
}
