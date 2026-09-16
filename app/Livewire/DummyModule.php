<?php

namespace App\Livewire;

use App\Contracts\CompanyContext;
use App\Services\CompanySettingsStore;
use App\Services\DynamicMenuRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DummyModule extends Component
{
    #[Locked]
    public string $module;

    #[Locked]
    public ?string $submodule = null;

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->submodule = $submodule;
    }

    public function render(
        CompanyContext $companyContext,
        CompanySettingsStore $settings,
        DynamicMenuRegistry $registry,
    ): View {
        abort_unless($registry->hasPath($this->module, $this->submodule), 404);
        abort_unless($registry->isModuleVisible($this->module), 403);

        $definition = $registry->routeDefinition($this->module, $this->submodule);
        abort_if($definition === null, 403);

        $company = $companyContext->current();
        $theme = (string) ($settings->read($company)['theme'] ?? 'a');

        return view('livewire.dummy-module', $definition + [
            'module' => $this->module,
            'submodule' => $this->submodule,
        ])
            ->layout('components.layouts.module', [
                'title' => $definition['label'],
                'theme' => $theme,
            ]);
    }
}
