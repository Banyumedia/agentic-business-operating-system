<?php

namespace App\Livewire\Public;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class LandingPage extends Component
{
    public function mount()
    {
        if (auth()->check()) {
            return redirect()->route('app.dashboard');
        }
    }

    public function render(): View
    {
        return view('livewire.public.landing-page', [
            // Nomor sales dibaca dari konfigurasi, dan tombolnya disembunyikan
            // bila belum diisi. Versi pertama halaman ini menanam nomor contoh
            // `6281234567890` di tiga tombol utama - calon pelanggan yang
            // menekannya akan sampai ke nomor asing, dan tidak ada yang tahu
            // karena halamannya tetap terlihat normal.
            'salesWhatsApp' => $this->salesWhatsApp(),
        ])->layout('components.layouts.guest');
    }

    private function salesWhatsApp(): ?string
    {
        $number = preg_replace('/[^0-9]/', '', (string) config('app.sales_whatsapp'));

        if ($number === null || $number === '') {
            return null;
        }

        return str_starts_with($number, '08') ? '628'.substr($number, 2) : $number;
    }
}
