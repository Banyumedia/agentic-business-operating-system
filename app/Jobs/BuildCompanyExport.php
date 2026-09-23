<?php

namespace App\Jobs;

use App\Models\BusinessNote;
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

        // T-107(g): tenant pemakai Obsidian bisa membuka vault-nya sendiri.
        // Ini TIDAK menggantikan business_notes.csv (yang sudah ikut lewat
        // katalog schema di atas, nol kode khusus) - ini format tambahan,
        // satu berkas .md per catatan, untuk tenant yang mau membaca arsipnya
        // sebagai catatan biasa, bukan tabel.
        $this->writeBusinessNotesAsMarkdown($company, $exportDir);

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

    /**
     * Satu berkas `.md` per catatan basis pengetahuan, dengan front-matter
     * YAML (judul, penulis, tanggal). Ditulis ke subfolder terpisah supaya
     * tidak bertabrakan nama dengan `.csv`/`.json` lain di root arsip.
     */
    private function writeBusinessNotesAsMarkdown(Company $company, string $exportDir): void
    {
        if (! Schema::hasTable('business_notes')) {
            return;
        }

        $notes = BusinessNote::where('company_id', $company->id)->get();
        if ($notes->isEmpty()) {
            return;
        }

        $notesDir = $exportDir.'/business_notes';
        if (! is_dir($notesDir)) {
            mkdir($notesDir, 0755, true);
        }

        foreach ($notes as $note) {
            $slug = Str::slug($note->title) ?: 'catatan-'.$note->id;
            $frontMatter = sprintf(
                "---\nid: %d\ntitle: %s\nauthor_type: %s\nsensitive: %s\ncreated_at: %s\nupdated_at: %s\n---\n\n",
                $note->id,
                json_encode($note->title),
                $note->author_type,
                $note->sensitive ? 'true' : 'false',
                $note->created_at?->toIso8601String() ?? '',
                $note->updated_at?->toIso8601String() ?? '',
            );

            file_put_contents(
                $notesDir.'/'.$note->id.'-'.$slug.'.md',
                $frontMatter.$note->content,
            );
        }
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

        // Subfolder markdown per catatan (T-107g) - glob root saja tidak
        // menjangkaunya, jadi dijalankan terpisah dengan path relatif
        // business_notes/{berkas}.
        foreach (glob($exportDir.'/business_notes/*.md') ?: [] as $file) {
            $zip->addFile($file, 'business_notes/'.basename($file));
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
