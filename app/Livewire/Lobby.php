<?php

namespace App\Livewire;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Services\DynamicMenuRegistry;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;
use LogicException;

class Lobby extends Component
{
    /** @return array<int, array{slug: string, name: string, icon: string, route: string}> */
    public function getAppsProperty(): array
    {
        try {
            return app(DynamicMenuRegistry::class)->visibleModules();
        } catch (InvalidArgumentException) {
            abort(404);
        } catch (LogicException) {
            return [];
        }
    }

    public function render(CompanyContext $companyContext, CompanySettingsStore $settings): View
    {
        try {
            $theme = (string) ($settings->read($companyContext->current())['theme'] ?? 'a');
        } catch (InvalidArgumentException) {
            abort(404);
        } catch (LogicException) {
            $theme = 'a';
        }

        return view('livewire.lobby')
            ->layout('layouts.app', ['theme' => $theme]);
    }
}
