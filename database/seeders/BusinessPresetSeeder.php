<?php

namespace Database\Seeders;

use App\Models\BusinessPreset;
use App\Services\Preset\PresetDefinitionValidator;
use Illuminate\Database\Seeder;
use JsonException;
use RuntimeException;

class BusinessPresetSeeder extends Seeder
{
    public function __construct(
        private readonly PresetDefinitionValidator $validator,
    ) {}

    public function run(): void
    {
        $directory = database_path('presets');

        if (! is_dir($directory) || ! is_readable($directory)) {
            throw new RuntimeException("Direktori preset tidak tersedia: {$directory}");
        }

        $paths = glob(rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'*.json');

        if ($paths === false) {
            throw new RuntimeException("Direktori preset tidak dapat dibaca: {$directory}");
        }

        foreach ($paths as $path) {
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

            $validated = $this->validator->validate($definition);

            BusinessPreset::updateOrCreate(
                ['key' => $validated['key']],
                [
                    'name' => $validated['name'],
                    'tier' => $validated['tier'],
                    'definition' => $validated,
                ]
            );
        }
    }
}
