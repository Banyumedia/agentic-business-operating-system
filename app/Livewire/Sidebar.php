<?php

namespace App\Livewire;

use App\Services\DynamicMenuRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Sidebar extends Component
{
    #[Locked]
    public string $module;

    public function mount(?string $module = null): void
    {
        $this->module = $module ?? (string) request()->segment(2);
    }

    /** @return array<int, array<string, string>> */
    public function getMenusProperty(): array
    {
        return app(DynamicMenuRegistry::class)->menusFor($this->module);
    }

    public function getTitleProperty(): string
    {
        return app(DynamicMenuRegistry::class)->titleFor($this->module);
    }

    public function render(): View
    {
        return view('livewire.sidebar');
    }
}
