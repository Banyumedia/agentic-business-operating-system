<?php

namespace App\Livewire\Widgets;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardWidget extends Component
{
    /** @var array<string, mixed> */
    public array $widget = [];

    /** @param array<string, mixed> $widget */
    public function mount(array $widget): void
    {
        $this->widget = $widget;
    }

    public function render(): View
    {
        return view('livewire.widgets.dashboard-widget');
    }
}
