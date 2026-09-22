<?php

namespace App\Livewire;

use App\Services\DynamicMenuRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Navigasi bawah untuk ponsel.
 *
 * Versi pertama navigasi ini menanam tiga tujuan tetap - Beranda, Kasir
 * (`/app/pos`), dan Buku Kas (`/app/accounting`) - tanpa memeriksa kapabilitas.
 * Pada usaha tanpa kapabilitas `pos` tab Kasir mengarah ke modul yang tidak
 * aktif, jadi pengguna ponsel menekannya dan mendapat 403. Sama untuk Buku Kas
 * pada usaha tanpa `finance.cashbook`.
 *
 * Karena itu isinya diturunkan dari `DynamicMenuRegistry` - sumber yang sama
 * dengan sidebar - sehingga tab hanya muncul untuk modul yang memang terjangkau
 * usaha itu. Menambah modul baru tidak perlu menyentuh berkas ini (D-31).
 */
class MobileQuickNav extends Component
{
    /** @return list<array{slug: string, name: string, route: string, active: bool}> */
    public function getTabsProperty(): array
    {
        $current = (string) request()->segment(2);
        $tabs = [];

        try {
            $modules = app(DynamicMenuRegistry::class)->visibleModules();
        } catch (\Throwable) {
            // Konteks company tidak dapat diselesaikan (mis. halaman dibuka
            // dengan company yang presetnya belum terkonfigurasi). Fail-closed
            // untuk sebuah bilah navigasi berarti **tidak menawarkan** tab,
            // bukan menjatuhkan seluruh halaman yang sudah dirender.
            return [];
        }

        foreach ($modules as $module) {
            if ($module['slug'] === 'settings') {
                // Pengaturan sudah punya jalannya sendiri lewat tombol Menu;
                // ia bukan tujuan yang sering ditekan saat melayani pelanggan.
                continue;
            }

            $tabs[] = [
                'slug' => $module['slug'],
                'name' => $module['name'],
                'route' => $module['route'],
                'active' => $module['slug'] === $current,
            ];
        }

        // Tiga tab plus tombol Menu; lebih dari itu tidak nyaman ditekan jempol.
        return array_slice($tabs, 0, 3);
    }

    public function render(): View
    {
        return view('livewire.mobile-quick-nav');
    }
}
