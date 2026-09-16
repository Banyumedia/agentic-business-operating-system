<?php

namespace App\Livewire;

use App\Services\DynamicMenuRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use LogicException;

class CommandPalette extends Component
{
    public string $search = '';

    public function render(DynamicMenuRegistry $registry): View
    {
        $results = [];

        if (mb_strlen($this->search) >= 2) {
            $needle = mb_strtolower($this->search);
            $results = collect($this->searchableMenus($registry))
                ->filter(fn (array $item): bool => str_contains(mb_strtolower($item['title']), $needle)
                    || str_contains(mb_strtolower($item['module']), $needle))
                ->take(8)
                ->values()
                ->all();
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
                        'icon' => '→',
                    ];
                }
            }

            foreach ($registry->menusFor('settings') as $menu) {
                $results[] = [
                    'title' => $menu['label'],
                    'module' => 'Pengaturan',
                    'type' => 'Menu',
                    'url' => $menu['route'],
                    'icon' => '→',
                ];
            }

            return $results;
        } catch (LogicException) {
            return [];
        }
    }
}
