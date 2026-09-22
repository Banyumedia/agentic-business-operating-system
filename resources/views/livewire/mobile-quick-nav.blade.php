<nav
    aria-label="Navigasi cepat ponsel"
    class="fixed bottom-0 left-0 right-0 z-40 flex h-16 items-center justify-around border-t border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-2 shadow-lg md:hidden"
>
    <a
        href="{{ route('app.dashboard') }}"
        wire:navigate
        @if (request()->routeIs('app.dashboard')) aria-current="page" @endif
        class="flex min-h-11 flex-1 flex-col items-center justify-center py-1 text-[var(--erp-text-secondary)] hover:text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] {{ request()->routeIs('app.dashboard') ? 'font-semibold text-[var(--erp-accent)]' : '' }}"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
            <polyline points="9 22 9 12 15 12 15 22" />
        </svg>
        <span class="mt-1 text-[11px]">Beranda</span>
    </a>

    {{-- Tab modul datang dari DynamicMenuRegistry, jadi tidak ada tab yang
         mengarah ke modul yang kapabilitasnya mati. --}}
    @foreach ($this->tabs as $tab)
        <a
            href="{{ $tab['route'] }}"
            wire:navigate
            @if ($tab['active']) aria-current="page" @endif
            class="flex min-h-11 flex-1 flex-col items-center justify-center py-1 text-[var(--erp-text-secondary)] hover:text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] {{ $tab['active'] ? 'font-semibold text-[var(--erp-accent)]' : '' }}"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <rect x="3" y="3" width="7" height="7" rx="1" />
                <rect x="14" y="3" width="7" height="7" rx="1" />
                <rect x="3" y="14" width="7" height="7" rx="1" />
                <rect x="14" y="14" width="7" height="7" rx="1" />
            </svg>
            <span class="mt-1 max-w-full truncate px-1 text-[11px]">{{ $tab['name'] }}</span>
        </a>
    @endforeach

    <button
        type="button"
        x-on:click="openSidebar()"
        class="flex min-h-11 flex-1 flex-col items-center justify-center py-1 text-[var(--erp-text-secondary)] hover:text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
        aria-label="Buka menu navigasi lengkap"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
        <span class="mt-1 text-[11px]">Menu</span>
    </button>
</nav>
