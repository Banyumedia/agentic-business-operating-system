<?php

namespace App\Livewire;

use Livewire\Component;

class Lobby extends Component
{
    public $apps = [
        ['name' => 'HRD', 'icon' => '👥', 'route' => '/app/hrd', 'color' => 'bg-blue-600'],
        ['name' => 'CRM', 'icon' => '💼', 'route' => '/app/crm', 'color' => 'bg-emerald-600'],
        ['name' => 'POS / Kasir', 'icon' => '🛒', 'route' => '/app/pos', 'color' => 'bg-purple-600'],
        ['name' => 'Akuntansi', 'icon' => '📊', 'route' => '/app/accounting', 'color' => 'bg-amber-600'],
        ['name' => 'Inventory', 'icon' => '📦', 'route' => '/app/inventory', 'color' => 'bg-indigo-600'],
        ['name' => 'Settings', 'icon' => '⚙️', 'route' => '/app/settings', 'color' => 'bg-slate-600'],
    ];

    public function render()
    {
        return view('livewire.lobby');
    }
}
