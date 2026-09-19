<aside
    id="module-sidebar"
    x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    x-bind:inert="!desktop && !sidebarOpen"
    x-bind:aria-hidden="(!desktop && !sidebarOpen).toString()"
    x-bind:role="desktop ? 'complementary' : 'dialog'"
    x-bind:aria-modal="!desktop && sidebarOpen ? 'true' : null"
    x-trap.noscroll="!desktop && sidebarOpen"
    class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-[var(--erp-border)] bg-[var(--erp-sidebar-bg)] text-[var(--erp-sidebar-text)] shadow-[var(--erp-card-shadow)] transition-transform duration-200 ease-out lg:translate-x-0"
    aria-labelledby="module-sidebar-title"
>
    <div class="flex items-center justify-between border-b border-[var(--erp-border)] px-5 py-5">
        <a href="{{ route('lobby') }}" wire:navigate class="min-w-0 flex-1 hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] rounded-[var(--erp-radius-sm)]">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--erp-sidebar-text)] opacity-70">Agentic BOS</p>
            <h2 id="module-sidebar-title" class="mt-1 truncate text-lg font-bold">{{ $this->title }}</h2>
        </a>
        <button
            x-ref="sidebarClose"
            type="button"
            x-on:click="closeSidebar()"
            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--erp-radius-md)] text-[var(--erp-sidebar-text)] hover:bg-[var(--erp-sidebar-active)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] lg:hidden"
            aria-label="Tutup navigasi modul"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto p-4" aria-label="Menu modul {{ $this->title }}">
        @foreach ($this->menus as $menu)
            @php
                $currentPath = request()->getPathInfo();
                $menuPath = parse_url($menu['route'], PHP_URL_PATH);
                $isModuleRoot = $currentPath === '/app/'.$module;
                $isOnlyMenuDescendant = $loop->count === 1 && str_starts_with($currentPath, rtrim($menuPath, '/').'/');
                $isActive = $currentPath === $menuPath || ($isModuleRoot && $loop->first) || $isOnlyMenuDescendant;
            @endphp
            <a
                href="{{ $menu['route'] }}"
                wire:navigate
                x-on:click="if (!desktop) closeSidebar()"
                title="{{ $menu['label'] }}"
                @if ($isActive) aria-current="page" @endif
                class="flex min-h-11 items-center gap-3 rounded-[var(--erp-radius-md)] px-4 py-3 text-sm font-medium text-[var(--erp-sidebar-text)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] {{ $isActive ? 'bg-[var(--erp-sidebar-active)]' : 'hover:bg-[var(--erp-sidebar-active)]' }}"
            >
                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-current opacity-70" aria-hidden="true"></span>
                <span>{{ $menu['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="border-t border-[var(--erp-border)] p-4">
        <a href="/app/settings" wire:navigate x-on:click="if (!desktop) closeSidebar()" title="Pengaturan" class="flex min-h-11 items-center gap-3 rounded-[var(--erp-radius-md)] px-4 py-3 text-sm font-medium text-[var(--erp-sidebar-text)] hover:bg-[var(--erp-sidebar-active)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="12" r="3" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.12 2.12-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V20.3h-3v-.08a1.7 1.7 0 0 0-1.03-1.56A1.7 1.7 0 0 0 8.8 19l-.06.06-2.12-2.12.06-.06A1.7 1.7 0 0 0 7 15a1.7 1.7 0 0 0-1.56-1.03H5.36v-3h.08A1.7 1.7 0 0 0 7 9.94a1.7 1.7 0 0 0-.34-1.88L6.6 8l2.12-2.12.06.06a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1.03-1.56V4.64h3v.08a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06L19.8 8l-.06.06a1.7 1.7 0 0 0-.34 1.88 1.7 1.7 0 0 0 1.56 1.03h.08v3h-.08A1.7 1.7 0 0 0 19.4 15Z" />
            </svg>
            Pengaturan
        </a>
        <a href="{{ route('lobby') }}" wire:navigate x-on:click="if (!desktop) closeSidebar()" title="Pilih modul lain" class="flex min-h-11 items-center gap-3 rounded-[var(--erp-radius-md)] px-4 py-3 text-sm font-medium text-[var(--erp-sidebar-text)] hover:bg-[var(--erp-sidebar-active)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 11.5 12 4l9 7.5M5.5 10v10h13V10" />
            </svg>
            Pilih modul lain
        </a>

        {{-- Logout POST + CSRF: GET logout tidak aman (bisa dipicu lintas situs). --}}
        <form method="POST" action="{{ route('logout') }}" x-on:click="if (!desktop) closeSidebar()" class="mt-1">
            @csrf
            <button type="submit" class="flex min-h-11 w-full items-center gap-3 rounded-[var(--erp-radius-md)] px-4 py-3 text-sm font-medium text-[var(--erp-sidebar-text)] hover:bg-[var(--erp-sidebar-active)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H9" />
                </svg>
                Keluar
            </button>
        </form>
    </div>
</aside>
