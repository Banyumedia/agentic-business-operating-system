<!DOCTYPE html>
<html lang="id" data-theme="{{ $theme ?? 'a' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? 'Agentic BOS' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-[var(--erp-bg-base)] text-[var(--erp-text-primary)] font-sans antialiased min-h-screen">

        <a href="#main-content" class="sr-only z-[70] rounded-[var(--erp-radius-sm)] bg-[var(--erp-accent)] px-4 py-3 text-[var(--erp-text-inverse)] focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:min-h-11">
            Lewati ke konten utama
        </a>

        @if(session()->has('admin_impersonation_id'))
            @php
                // F4 (QA MQ-01): gunakan kontrak displayName() (MQ-01C4), bukan
                // getCompany()->name langsung. Konteks gagal tidak boleh
                // meruntuhkan layout.
                $bannerCompany = 'Klien';
                try {
                    $bannerCompany = app(\App\Contracts\CompanyContext::class)->displayName();
                } catch (\Throwable) {
                    $bannerCompany = 'Klien';
                }
            @endphp
            <div class="bg-[var(--erp-warning)] text-[var(--erp-bg-inset)] text-center py-2 px-4 font-bold flex justify-between items-center gap-4 z-50 relative sticky top-0">
                <span>Anda login sebagai {{ $bannerCompany }} - mode Bantuan Admin</span>
                <form method="POST" action="{{ route('admin.impersonate.stop') }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-[var(--erp-radius-sm)] bg-[var(--erp-bg-inset)] px-3 text-sm text-[var(--erp-warning)] hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">Akhiri Sesi Bantuan</button>
                </form>
            </div>
        @endif

        {{ $slot }}

        <livewire:command-palette />

        @livewireScripts
    </body>
</html>
