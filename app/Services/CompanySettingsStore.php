<?php

namespace App\Services;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CompanySettingsStore
{
    /** @return array<string, mixed> */
    public function read(string $company): array
    {
        $path = $this->path($company);
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return [];
        }

        $settings = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($settings) ? $settings : [];
    }

    /**
     * @param  Closure(array<string, mixed>): array<string, mixed>  $update
     * @return array<string, mixed>
     */
    public function update(string $company, Closure $update): array
    {
        $path = $this->path($company);
        $disk = Storage::disk('local');
        $fullPath = $disk->path($path);
        $filesystem = app(Filesystem::class);
        $filesystem->ensureDirectoryExists(dirname($fullPath));

        $lock = fopen($fullPath.'.lock', 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw new InvalidArgumentException('Pengaturan usaha tidak dapat dikunci.');
        }

        try {
            $settings = $this->read($company);
            $settings = $update($settings);
            $filesystem->replace(
                $fullPath,
                json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
            );
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $settings;
    }

    private function path(string $company): string
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $company)) {
            throw new InvalidArgumentException('Identitas usaha tidak valid.');
        }

        return "json/$company/settings.json";
    }
}
