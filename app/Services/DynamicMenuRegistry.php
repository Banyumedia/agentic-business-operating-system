<?php

namespace App\Services;

/**
 * Sumber tunggal struktur menu per modul (App Switcher ala Odoo/Zoho).
 *
 * Fase ini masih memakai katalog statis. Struktur sengaja dipisahkan dari
 * komponen Livewire agar penambahan feature flag per company (T-15/T-16)
 * cukup mengubah kelas ini tanpa menyentuh view.
 *
 * Prinsip zero-bloat: modul yang tidak dikenal menghasilkan menu KOSONG,
 * bukan placeholder, sehingga tidak ada DOM yang dirender sia-sia.
 */
class DynamicMenuRegistry
{
    private const DEFAULT_ACCENT = 'bg-slate-600';

    /**
     * @var array<string, array{accent: string, items: array<int, array{label: string, icon: string, route: string}>}>
     */
    private array $modules = [
        'hrd' => [
            'accent' => 'bg-blue-600',
            'items' => [
                ['label' => 'Dashboard HRD', 'icon' => '🏠', 'route' => '/app/hrd'],
                ['label' => 'Data Karyawan', 'icon' => '👥', 'route' => '/app/hrd/employees'],
                ['label' => 'Presensi & Cuti', 'icon' => '⏱️', 'route' => '/app/hrd/attendance'],
                ['label' => 'Payroll', 'icon' => '💰', 'route' => '/app/hrd/payroll'],
            ],
        ],
        'crm' => [
            'accent' => 'bg-emerald-600',
            'items' => [
                ['label' => 'Dashboard CRM', 'icon' => '🏠', 'route' => '/app/crm'],
                ['label' => 'Data Klien', 'icon' => '🏢', 'route' => '/app/crm/clients'],
                ['label' => 'Follow Up', 'icon' => '📞', 'route' => '/app/crm/followup'],
                ['label' => 'Penjualan', 'icon' => '📈', 'route' => '/app/crm/sales'],
            ],
        ],
        'pos' => [
            'accent' => 'bg-purple-600',
            'items' => [
                ['label' => 'Kasir (POS)', 'icon' => '🛒', 'route' => '/app/pos'],
                ['label' => 'Riwayat Transaksi', 'icon' => '🧾', 'route' => '/app/pos/history'],
            ],
        ],
        'accounting' => [
            'accent' => 'bg-amber-600',
            'items' => [
                ['label' => 'Dashboard Keuangan', 'icon' => '🏠', 'route' => '/app/accounting'],
                ['label' => 'Buku Kas', 'icon' => '📒', 'route' => '/app/accounting/cashbook'],
                ['label' => 'Laporan', 'icon' => '📊', 'route' => '/app/accounting/reports'],
            ],
        ],
        'inventory' => [
            'accent' => 'bg-indigo-600',
            'items' => [
                ['label' => 'Dashboard Inventory', 'icon' => '🏠', 'route' => '/app/inventory'],
                ['label' => 'Daftar Barang', 'icon' => '📦', 'route' => '/app/inventory/items'],
                ['label' => 'Stok Masuk & Keluar', 'icon' => '🔄', 'route' => '/app/inventory/movements'],
            ],
        ],
        'settings' => [
            'accent' => 'bg-slate-600',
            'items' => [
                ['label' => 'Profil Bisnis', 'icon' => '🏢', 'route' => '/app/settings'],
                ['label' => 'Fitur & Modul', 'icon' => '🧩', 'route' => '/app/settings/features'],
                ['label' => 'Tim & Akses', 'icon' => '👤', 'route' => '/app/settings/team'],
            ],
        ],
    ];

    /**
     * Daftar slug modul yang dikenal.
     *
     * @return array<int, string>
     */
    public function modules(): array
    {
        return array_keys($this->modules);
    }

    public function hasModule(?string $module): bool
    {
        return $module !== null && array_key_exists($module, $this->modules);
    }

    /**
     * Menu untuk sebuah modul. Modul tak dikenal menghasilkan array kosong.
     *
     * @return array<int, array{label: string, icon: string, route: string}>
     */
    public function menusFor(?string $module): array
    {
        if (! $this->hasModule($module)) {
            return [];
        }

        return $this->modules[$module]['items'];
    }

    /**
     * Warna aksen modul, dengan fallback netral yang aman.
     */
    public function accentFor(?string $module): string
    {
        if (! $this->hasModule($module)) {
            return self::DEFAULT_ACCENT;
        }

        return $this->modules[$module]['accent'];
    }
}
