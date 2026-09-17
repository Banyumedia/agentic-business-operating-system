<?php

namespace App\Livewire;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use LogicException;

class CommandPalette extends Component
{
    /** Batas hasil bertipe "Data" per pencarian, terlepas dari jumlah entity yang cocok. */
    private const MAX_DATA_RESULTS = 8;

    /** Batas hasil per entity agar satu entity tidak mendominasi daftar data. */
    private const MAX_PER_ENTITY = 3;

    /** Batas hasil bertipe "Menu" per pencarian, terpisah dari batas data. */
    private const MAX_MENU_RESULTS = 8;

    public string $search = '';

    public function render(DynamicMenuRegistry $registry, CompanyContext $companyContext, EntityRepository $repository): View
    {
        $results = [];

        if (mb_strlen($this->search) >= 2) {
            // Total hasil bisa mencapai MAX_MENU_RESULTS + MAX_DATA_RESULTS
            // (saat ini 8 + 8 = 16): menu dan data adalah dua kategori
            // terpisah dengan batasnya sendiri, bukan satu kuota gabungan.
            $needle = mb_strtolower($this->search);
            $menuResults = collect($this->searchableMenus($registry))
                ->filter(fn (array $item): bool => str_contains(mb_strtolower($item['title']), $needle)
                    || str_contains(mb_strtolower($item['module']), $needle))
                ->take(self::MAX_MENU_RESULTS)
                ->values()
                ->all();

            $results = [...$menuResults, ...$this->searchEntityData($registry, $companyContext, $repository)];
        }

        return view('livewire.command-palette', ['results' => $results]);
    }

    /** @return array<int, array{title: string, module: string, type: string, url: string, icon: string}> */
    private function searchableMenus(DynamicMenuRegistry $registry): array
    {
        try {
            $results = [];

            foreach ($registry->visibleModules() as $module) {
                foreach ($registry->menusFor($module['slug']) as $menu) {
                    $results[] = [
                        'title' => $menu['label'],
                        'module' => $module['name'],
                        'type' => 'Menu',
                        'url' => $menu['route'],
                        'icon' => '?',
                    ];
                }
            }

            foreach ($registry->menusFor('settings') as $menu) {
                $results[] = [
                    'title' => $menu['label'],
                    'module' => 'Pengaturan',
                    'type' => 'Menu',
                    'url' => $menu['route'],
                    'icon' => '?',
                ];
            }

            return $results;
        } catch (LogicException) {
            return [];
        }
    }

    /**
     * Mencari data lintas entity yang kapabilitasnya aktif untuk company aktif.
     *
     * Cakupan entity dibatasi pada yang benar-benar tampil di menu (registry
     * yang sudah menegakkan D-31/zero-bloat), bukan seluruh skema - sehingga
     * kapabilitas yang mati otomatis tidak pernah tersentuh di sini. `MAX_PER_ENTITY`
     * dan `MAX_DATA_RESULTS` membatasi jumlah HASIL yang ditampilkan, bukan
     * jumlah baris JSON yang dipindai - `EntityRepository::query()` tetap
     * membaca seluruh isi file entity company itu sebelum memfilter dan
     * memotong hasilnya.
     *
     * Tidak ada catch generik di jalur repository di sini secara sengaja:
     * entity yang dirujuk menu tetapi schema-nya hilang/rusak (defect
     * registry), file JSON yang korup, row yang melanggar schema, atau lock
     * yang gagal harus menggagalkan request secara terlihat (fail-closed),
     * bukan diam-diam tampil sebagai "tidak ada hasil" data - kegagalan diam
     * seperti itu pernah menyamarkan defect registry `timesheets` (T-F13R).
     * Satu-satunya kondisi yang ditangkap adalah company aktif belum
     * terpilih sama sekali, kontrak jelas dari `CompanyContext` untuk kondisi
     * normal (mis. halaman lobby sebelum company dipilih), bukan korupsi data.
     *
     * @return array<int, array{title: string, module: string, type: string, url: string, icon: string}>
     */
    private function searchEntityData(DynamicMenuRegistry $registry, CompanyContext $companyContext, EntityRepository $repository): array
    {
        try {
            $company = $companyContext->current();
        } catch (LogicException) {
            return [];
        }

        $presenter = app(SchemaPresenter::class);
        $results = [];

        foreach ($this->searchableEntities($registry) as $entity => $target) {
            $schema = EntitySchema::load($entity);

            $page = $repository->for($company, $entity)->query([
                '_search' => $this->search,
                '_per_page' => self::MAX_PER_ENTITY,
            ]);

            $titleField = $presenter->titleField($schema);

            foreach ($page['data'] as $row) {
                $title = $titleField !== null ? ($row[$titleField] ?? null) : null;

                $results[] = [
                    'title' => is_string($title) && trim($title) !== '' ? $title : '#'.$row['id'],
                    'module' => $target['module'],
                    'type' => 'Data',
                    'url' => $target['route'],
                    'icon' => '?',
                ];

                if (count($results) >= self::MAX_DATA_RESULTS) {
                    return $results;
                }
            }
        }

        return $results;
    }

    /**
     * Memetakan entity ke satu route representatif yang benar-benar dapat
     * dibuka (bukan URL per-baris palsu - pola layar Fase 2 belum punya
     * halaman detail per baris). Modul `dashboard`/`settings` dikecualikan
     * karena entity di sana adalah ringkasan tunggal, bukan koleksi baris.
     *
     * @return array<string, array{route: string, module: string, preferred: bool}>
     */
    private function searchableEntities(DynamicMenuRegistry $registry): array
    {
        $entities = [];
        $preferredScreens = ['list', 'ledger', 'pipeline', 'calendar'];

        foreach ($registry->visibleModules() as $module) {
            if (in_array($module['slug'], ['dashboard', 'settings'], true)) {
                continue;
            }

            foreach ($registry->menusFor($module['slug']) as $item) {
                $entity = $item['entity'];
                $isPreferred = in_array($item['screen'], $preferredScreens, true);

                if (! isset($entities[$entity]) || ($isPreferred && ! $entities[$entity]['preferred'])) {
                    $entities[$entity] = [
                        'route' => $item['route'],
                        'module' => $module['name'],
                        'preferred' => $isPreferred,
                    ];
                }
            }
        }

        return $entities;
    }
}
