<?php

namespace App\Jobs;

use App\Models\Company;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Arsip data satu company untuk hak portabilitas data (D-51).
 *
 * Daftar tabel TIDAK ditulis di sini: ia diturunkan dari katalog schema entitas
 * (`database/schemas/*.schema.json`). Dengan begitu entitas baru - misalnya
 * tagihan pelanggan beserta barisnya - langsung ikut terekspor tanpa ada yang
 * perlu ingat menyunting job ini. Sebelumnya hanya empat berkas yang diekspor,
 * sehingga data uang tenant tertinggal di dalam sistem.
 */
class BuildCompanyExport implements ShouldQueue
{
    use Queueable;

    /** Nama model yang tidak dapat diturunkan langsung dari nama entitas. */
    private const MODEL_ALIASES = [
        'item_batches' => 'ItemBatch',
        'cash_entries' => 'CashEntry',
        'pos_shifts' => 'PosShift',
    ];

    public function __construct(
        public string $companyId,
        public int $userId
    ) {}

    public function handle(): void
    {
        $company = Company::with(['identities', 'memberships', 'settings'])->find($this->companyId);
        if (! $company || $company->owner_user_id !== $this->userId) {
            return;
        }

        $exportDir = storage_path('app/exports/'.$this->companyId);
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $this->writeJson($exportDir.'/identities.json', $company->identities->toArray());
        $this->writeJson($exportDir.'/settings.json', $company->settings->toArray());
        $this->writeJson($exportDir.'/memberships.json', $company->memberships->toArray());

        $manifest = [];

        foreach ($this->exportableEntities() as $entity => $modelClass) {
            /** @var Model $model */
            $model = new $modelClass;

            $rows = $model->newQuery()->where('company_id', $company->id)->get();
            $this->exportModelToCsv($rows, $exportDir.'/'.$entity.'.csv');
            $manifest[$entity] = $rows->count();
        }

        $this->writeJson($exportDir.'/manifest.json', [
            'company_id' => $company->id,
            'exported_at' => now()->toIso8601String(),
            'entities' => $manifest,
        ]);

        $this->archive($exportDir);
    }

    /**
     * Entitas yang benar-benar dapat diekspor: punya schema, punya model
     * Eloquent, dan tabelnya memang ber-`company_id` (D-26). Yang tidak
     * memenuhi dilewati tanpa menggagalkan seluruh arsip.
     *
     * @return array<string, class-string<Model>>
     */
    private function exportableEntities(): array
    {
        $entities = [];

        foreach (glob(database_path('schemas/*.schema.json')) ?: [] as $path) {
            $entity = str_replace('.schema.json', '', basename($path));
            $class = 'App\\Models\\'.(self::MODEL_ALIASES[$entity] ?? Str::studly(Str::singular($entity)));

            if (! class_exists($class)) {
                continue;
            }

            /** @var Model $model */
            $model = new $class;

            if (! Schema::hasTable($model->getTable()) || ! Schema::hasColumn($model->getTable(), 'company_id')) {
                continue;
            }

            $entities[$entity] = $class;
        }

        ksort($entities);

        return $entities;
    }

    private function archive(string $exportDir): void
    {
        $zipPath = $exportDir.'/export.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

        foreach (glob($exportDir.'/*.*') ?: [] as $file) {
            if (basename($file) !== 'export.zip') {
                $zip->addFile($file, basename($file));
            }
        }

        $zip->close();
    }

    /** @param array<array-key, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
    }

    /** @param Collection<int, Model> $collection */
    private function exportModelToCsv(Collection $collection, string $path): void
    {
        if ($collection->isEmpty()) {
            file_put_contents($path, '');

            return;
        }

        $file = fopen($path, 'w');
        $headers = array_keys($collection->first()->getAttributes());
        fputcsv($file, $headers);

        foreach ($collection as $item) {
            $row = [];
            foreach ($headers as $header) {
                $value = $item->{$header};
                $row[] = is_array($value) || is_object($value) ? json_encode($value) : $value;
            }
            fputcsv($file, $row);
        }

        fclose($file);
    }
}
