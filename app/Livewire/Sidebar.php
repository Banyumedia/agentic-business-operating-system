<?php

namespace App\Livewire;

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
        $menus = [
            'hrd' => [
                ['label' => 'Dashboard HRD', 'icon' => '🏠', 'route' => '/app/hrd'],
                ['label' => 'Data Karyawan', 'icon' => '👥', 'route' => '/app/hrd/employees'],
                ['label' => 'Presensi & Cuti', 'icon' => '⏱️', 'route' => '/app/hrd/attendance'],
                ['label' => 'Payroll', 'icon' => '💰', 'route' => '/app/hrd/payroll'],
            ],
            'crm' => [
                ['label' => 'Dashboard CRM', 'icon' => '🏠', 'route' => '/app/crm'],
                ['label' => 'Data Klien', 'icon' => '🏢', 'route' => '/app/crm/clients'],
                ['label' => 'Follow Up', 'icon' => '📞', 'route' => '/app/crm/followup'],
                ['label' => 'Penjualan', 'icon' => '📈', 'route' => '/app/crm/sales'],
            ],
            'pos' => [
                ['label' => 'Kasir (POS)', 'icon' => '🛒', 'route' => '/app/pos'],
                ['label' => 'Riwayat Transaksi', 'icon' => '🧾', 'route' => '/app/pos/history'],
            ],
        ];

        return $menus[$this->module] ?? [
             ['label' => 'Dashboard', 'icon' => '🏠', 'route' => '/app/'.$this->module],
             ['label' => 'Menu 1', 'icon' => '📄', 'route' => '#'],
             ['label' => 'Menu 2', 'icon' => '⚙️', 'route' => '#'],
        ];
    }

    public function render()
    {
        return view('livewire.sidebar');
    }
}
