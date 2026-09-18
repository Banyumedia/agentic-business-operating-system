<?php

namespace App\Livewire;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Laravel\Scout\Builder;
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

        $isEloquent = config('datasource.driver') === 'eloquent';

        foreach ($this->searchableEntities($registry) as $entity => $target) {
            $schema = EntitySchema::load($entity);
            $titleField = $presenter->titleField($schema);

            if ($isEloquent) {
                // Scout Eloquent search
                $modelClass = 'App\\Models\\'.Str::studly(Str::singular($entity));
                if (class_exists($modelClass) && method_exists($modelClass, 'search')) {
                    /** @var \Illuminate\Database\Eloquent\Builder|Builder $searchBuilder */
                    $searchBuilder = $modelClass::search($this->search)->where('company_id', $company);
                    $records = $searchBuilder->take(self::MAX_PER_ENTITY)->get();

                    foreach ($records as $row) {
                        $title = $titleField !== null ? ($row->{$titleField} ?? null) : null;

                        $results[] = [
                            'title' => is_string($title) && trim($title) !== '' ? $title : '#'.$row->id,
                            'module' => $target['module'],
                            'type' => 'Data',
                            'url' => $target['route'],
                            'icon' => '?',
                        ];

                        if (count($results) >= self::MAX_DATA_RESULTS) {
                            return $results;
                        }
                    }

                    continue; // Skip the JSON repo fallback for this entity
                }
            }

            // Fallback to EntityRepository (JSON or Unimplemented Eloquent)
            $page = $repository->for($company, $entity)->query([
                '_search' => $this->search,
                '_per_page' => self::MAX_PER_ENTITY,
            ]);

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
