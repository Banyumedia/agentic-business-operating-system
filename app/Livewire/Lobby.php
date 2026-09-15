<?php

namespace App\Livewire;

use Livewire\Component;

class Lobby extends Component
{
    /**
     * Katalog aplikasi pada App Switcher.
     *
     * `slug` adalah identitas modul yang dipakai untuk membangun URL melalui
     * route bernama `app.module`, sehingga path tidak di-hardcode di view.
     */
    public $apps = [
        ['name' => 'HRD', 'slug' => 'hrd', 'icon' => '👥', 'color' => 'bg-blue-600'],
        ['name' => 'CRM', 'slug' => 'crm', 'icon' => '💼', 'color' => 'bg-emerald-600'],
        ['name' => 'POS / Kasir', 'slug' => 'pos', 'icon' => '🛒', 'color' => 'bg-purple-600'],
        ['name' => 'Akuntansi', 'slug' => 'accounting', 'icon' => '📊', 'color' => 'bg-amber-600'],
        ['name' => 'Inventory', 'slug' => 'inventory', 'icon' => '📦', 'color' => 'bg-indigo-600'],
        ['name' => 'Settings', 'slug' => 'settings', 'icon' => '⚙️', 'color' => 'bg-slate-600'],
    ];

    public function render()
    {
        return view('livewire.lobby');
    }
}
