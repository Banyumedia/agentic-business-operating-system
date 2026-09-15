<?php

namespace App\Livewire;

use App\Services\DynamicMenuRegistry;
use Livewire\Component;

class Sidebar extends Component
{
    public $module;

    public function mount($module = null)
    {
        $this->module = $module ?? request()->segment(2);
    }

    public function getMenusProperty()
    {
        return app(DynamicMenuRegistry::class)->menusFor($this->module);
    }

    public function getAccentProperty(): string
    {
        return app(DynamicMenuRegistry::class)->accentFor($this->module);
    }

    public function getIsKnownModuleProperty(): bool
    {
        return app(DynamicMenuRegistry::class)->hasModule($this->module);
    }

    public function render()
    {
        return view('livewire.sidebar');
    }
}
