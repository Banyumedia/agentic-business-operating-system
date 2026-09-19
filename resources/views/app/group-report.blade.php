@php
$theme = $company->theme ?? 'a';
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="{{ $theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Laporan Gabungan Grup Cabang - Agentic BOS</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-[var(--erp-bg-base)] text-[var(--erp-text-primary)] font-sans antialiased min-h-screen">
        <div class="mx-auto max-w-4xl p-4 sm:p-8">
            <h1 class="mb-2 text-2xl font-bold sm:text-3xl">Laporan Gabungan Grup Cabang</h1>
            <p class="mb-6 text-sm text-[var(--erp-text-secondary)] sm:text-base">Grup: {{ $company->parentCompany->name ?? $company->name }}</p>

            {{-- dl semantik: dibaca sebagai daftar label-nilai oleh pembaca
                layar, dan tetap satu kolom (grid-cols-1) sampai layar cukup
                lebar - tidak ada data yang hilang di layar kecil. --}}
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)]">
                    <dt class="text-lg font-semibold text-[var(--erp-text-secondary)]">Total Kontak</dt>
                    <dd class="mt-2 text-3xl font-bold">{{ $aggregate['total_contacts'] ?? 0 }}</dd>
                </div>
                <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)]">
                    <dt class="text-lg font-semibold text-[var(--erp-text-secondary)]">Total Catatan Kas</dt>
                    <dd class="mt-2 text-3xl font-bold">{{ $aggregate['total_cash_entries'] ?? 0 }}</dd>
                </div>
            </dl>

            <div class="mt-8">
                {{-- `/app` bukan rute; kembali ke dashboard bernama (D-24). --}}
                <a href="{{ route('app.dashboard') }}" class="inline-flex min-h-11 items-center text-[var(--erp-accent)] underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">Kembali ke App</a>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
