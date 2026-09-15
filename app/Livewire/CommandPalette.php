<?php

namespace App\Livewire;

use Livewire\Component;

class CommandPalette extends Component
{
    public $search = '';

    // Data tiruan / dummy untuk di-search
    private $dummyData = [
        ['title' => 'Data Karyawan', 'module' => 'HRD', 'type' => 'Menu', 'url' => '/app/hrd/employees', 'icon' => '👥'],
        ['title' => 'Budi Santoso', 'module' => 'HRD', 'type' => 'Karyawan', 'url' => '#', 'icon' => '👤'],
        ['title' => 'Siti Aminah', 'module' => 'HRD', 'type' => 'Karyawan', 'url' => '#', 'icon' => '👤'],
        ['title' => 'Invoice #INV-1024', 'module' => 'Akuntansi', 'type' => 'Tagihan', 'url' => '#', 'icon' => '🧾'],
        ['title' => 'Laporan Keuangan', 'module' => 'Akuntansi', 'type' => 'Menu', 'url' => '#', 'icon' => '📊'],
        ['title' => 'PT Makmur Jaya', 'module' => 'CRM', 'type' => 'Klien', 'url' => '#', 'icon' => '🏢'],
        ['title' => 'Settings', 'module' => 'Sistem', 'type' => 'Menu', 'url' => '/app/settings', 'icon' => '⚙️'],
    ];

    public function render()
    {
        $results = [];

        if (strlen($this->search) >= 2) {
            $results = collect($this->dummyData)->filter(function ($item) {
                return stripos($item['title'], $this->search) !== false
                    || stripos($item['module'], $this->search) !== false;
            })->take(5)->values()->toArray();
        }

        return view('livewire.command-palette', [
            'results' => $results,
        ]);
    }
}
